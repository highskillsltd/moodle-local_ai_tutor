<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Unit tests for local_ai_tutor\file_extractor.
 *
 * @package   local_ai_tutor
 * @copyright 2026 Highskills and more <info@highskills.co.il>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ai_tutor;

/**
 * Tests for file_extractor.
 *
 * @package   local_ai_tutor
 * @covers    \local_ai_tutor\file_extractor
 */
final class file_extractor_test extends \advanced_testcase {
    /**
     * Test extract returns raw content for .txt files.
     *
     * @covers ::extract
     */
    public function test_extract_txt(): void {
        $tmp = tempnam(sys_get_temp_dir(), 'aiassist_');
        file_put_contents($tmp, 'Hello world');
        $result = file_extractor::extract($tmp, 'txt');
        unlink($tmp);
        $this->assertSame('Hello world', $result);
    }

    /**
     * Test extract strips HTML tags for .html files.
     *
     * @covers ::extract
     */
    public function test_extract_html_strips_tags(): void {
        $tmp = tempnam(sys_get_temp_dir(), 'aiassist_');
        file_put_contents($tmp, '<p>Hello <b>World</b></p>');
        $result = file_extractor::extract($tmp, 'html');
        unlink($tmp);
        $this->assertSame('Hello World', $result);
    }

    /**
     * Test extract returns empty string for unknown extensions.
     *
     * @covers ::extract
     */
    public function test_extract_unknown_returns_empty(): void {
        $tmp = tempnam(sys_get_temp_dir(), 'aiassist_');
        file_put_contents($tmp, 'some data');
        $result = file_extractor::extract($tmp, 'xyz');
        unlink($tmp);
        $this->assertSame('', $result);
    }

    /**
     * Test extract dispatches .docx to extract_zip_xml (word/document.xml entry).
     *
     * @covers ::extract
     */
    public function test_extract_docx_reads_document_xml(): void {
        $tmp = tempnam(sys_get_temp_dir(), 'aiassist_') . '.docx';
        $zip = new \ZipArchive();
        $zip->open($tmp, \ZipArchive::CREATE);
        $zip->addFromString('word/document.xml', '<w:t>Docx Content</w:t>');
        $zip->close();
        $result = file_extractor::extract($tmp, 'docx');
        unlink($tmp);
        $this->assertStringContainsString('Docx Content', $result);
    }

    /**
     * Test extract dispatches .pptx to extract_pptx, reading slides in numeric order.
     *
     * @covers ::extract
     * @covers ::extract_pptx
     */
    public function test_extract_pptx_reads_slides_in_order(): void {
        $tmp = tempnam(sys_get_temp_dir(), 'aiassist_') . '.pptx';
        $zip = new \ZipArchive();
        $zip->open($tmp, \ZipArchive::CREATE);
        // Added out of order to verify numeric (not lexical) sort — slide10 must sort after slide2.
        $zip->addFromString('ppt/slides/slide10.xml', '<a:t>Slide Ten</a:t>');
        $zip->addFromString('ppt/slides/slide2.xml', '<a:t>Slide Two</a:t>');
        $zip->addFromString('ppt/slides/slide1.xml', '<a:t>Slide One</a:t>');
        $zip->close();

        $result = file_extractor::extract($tmp, 'pptx');
        unlink($tmp);

        $this->assertSame(
            strpos($result, 'Slide One'),
            0
        );
        $this->assertLessThan(strpos($result, 'Slide Ten'), strpos($result, 'Slide Two'));
    }

    /**
     * Test extract_pptx returns empty string for an invalid (non-ZIP) file.
     *
     * @covers ::extract_pptx
     */
    public function test_extract_pptx_invalid_file(): void {
        $tmp = tempnam(sys_get_temp_dir(), 'aiassist_');
        file_put_contents($tmp, 'not a zip');
        $result = file_extractor::extract_pptx($tmp);
        unlink($tmp);
        $this->assertSame('', $result);
    }

    /**
     * Test extract_pdf returns a string without throwing for any input.
     *
     * @covers ::extract_pdf
     */
    public function test_extract_pdf_returns_string(): void {
        $tmp = tempnam(sys_get_temp_dir(), 'aiassist_');
        file_put_contents($tmp, '%PDF-1.4 fake content');
        $result = file_extractor::extract_pdf($tmp);
        unlink($tmp);
        $this->assertIsString($result);
    }
}
