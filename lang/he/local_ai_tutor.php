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
 * Hebrew language strings for the AI Tutor plugin.
 *
 * @package    local_ai_tutor
 * @copyright  2026 Highskills and more <info@highskills.co.il>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['ai_tutor:use'] = 'שימוש במורה ה-AI';
$string['ai_tutor:viewinsights'] = 'צפייה בדפוסי קושי ובפערי תוכן של מורה ה-AI';
$string['api_not_configured'] = 'מורה ה-AI אינו מוגדר. יש לפנות למנהל המערכת.';
$string['apierror'] = 'שרת מורה ה-AI החזיר שגיאה: {$a}';
$string['apikey'] = 'מפתח API (Bearer token)';
$string['apikey_desc'] = 'מפתח ה-API של הלקוח כפי שמוצג בפאנל הניהול של Foundry לאחר יצירה או חידוש.';
$string['chatplaceholder'] = 'שאל/י שאלה על הקורס…';
$string['closebutton'] = 'סגירה';
$string['col_count'] = 'מספר פעמים שנשאל';
$string['col_question'] = 'שאלה';
$string['col_studentcount'] = 'סטודנטים שהתקשו';
$string['col_students'] = 'סטודנטים';
$string['col_topic'] = 'נושא';
$string['connectsthedots'] = 'קישורים נוספים';
$string['contentgaps'] = 'פערי תוכן';
$string['curlerror'] = 'לא ניתן להתחבר לשרת מורה ה-AI: {$a}';
$string['customfieldcategory'] = 'מורה AI';
$string['enableforcourse'] = 'הפעלת מורה AI';
$string['foundryurl'] = 'כתובת URL של Foundry';
$string['foundryurl_desc'] = 'כתובת מלאה עד וכולל מזהה הלקוח וקוד המשימה, לדוגמה https://your-host/api/v1/{tenant-uuid}/private-tutor — התוסף מוסיף בעצמו /chat.';
$string['greeting'] = 'שלום {$a}, שאל/י אותי על כל דבר בקורס הזה.';
$string['httpstatuslabel'] = 'HTTP';
$string['insights'] = 'תובנות מורה AI';
$string['nogapsyet'] = 'טרם נרשמו פערי תוכן.';
$string['nostrugglesyet'] = 'טרם נרשמו דפוסי קושי.';
$string['pluginname'] = 'מורה AI';
$string['position_bottomleft'] = 'למטה משמאל';
$string['position_bottomright'] = 'למטה מימין';
$string['position_topleft'] = 'למעלה משמאל';
$string['position_topright'] = 'למעלה מימין';
$string['practiceproblems'] = 'שאלות תרגול';
$string['privacy:metadata:foundry'] = 'כדי לענות על שאלה, השאלה ותוכן הקורס נשלחים לשירות AI חיצוני (Foundry) המוגדר על ידי מנהל המערכת.';
$string['privacy:metadata:foundry:content'] = 'תוכן קורס בטקסט רגיל (עמודים, פוסטים בפורום, טקסט מקבצים וכו׳), נשלח פעם אחת לכל שיחה — לעולם לא כולל הגשות, ציונים או ניסיונות של סטודנטים.';
$string['privacy:metadata:foundry:course_lang'] = 'שפת הקורס, נשלחת כדי שהתשובה תיווצר בשפה הנכונה.';
$string['privacy:metadata:foundry:question'] = 'השאלה שהוקלדה על ידי הסטודנט.';
$string['privacy:metadata:foundry:recent_questions'] = 'השאלות האחרונות של הסטודנט בשיחה זו, נשלחות כדי לזהות קושי.';
$string['privacy:metadata:foundry:session_id'] = 'מזהה אקראי לשיחת הצ׳אט, נוצר בדפדפן.';
$string['privacy:metadata:local_ai_tutor_turns'] = 'מידע על כל תשובה שנתן מורה ה-AI לסטודנט, המשמש לבניית תצוגות דפוסי הקושי ופערי התוכן למרצה.';
$string['privacy:metadata:local_ai_tutor_turns:in_scope'] = 'האם ניתן היה לענות על השאלה מתוך תוכן הקורס.';
$string['privacy:metadata:local_ai_tutor_turns:primary_citation_id'] = 'המקור בעל רמת הביטחון הגבוהה ביותר עבור התשובה, אם קיים.';
$string['privacy:metadata:local_ai_tutor_turns:question'] = 'השאלה שהסטודנט שאל.';
$string['privacy:metadata:local_ai_tutor_turns:sessionid'] = 'שיחת הצ׳אט שבה נשאלה השאלה.';
$string['privacy:metadata:local_ai_tutor_turns:stuck'] = 'האם נראה שהסטודנט התקשה בשאלה זו.';
$string['privacy:metadata:local_ai_tutor_turns:timecreated'] = 'מועד השאלה.';
$string['privacy:metadata:local_ai_tutor_turns:userid'] = 'מזהה הסטודנט ששאל את השאלה.';
$string['send'] = 'שליחה';
$string['streamtimeout'] = 'זמן קצוב להזרמה (שניות)';
$string['streamtimeout_desc'] = 'זמן ההמתנה המרבי לסיום הזרמת תשובה מהשרת. מינימום 30 שניות.';
$string['strugglepatterns'] = 'דפוסי קושי';
$string['task_rebuildcontentcache'] = 'בנייה מחדש של מטמון תוכן מורה ה-AI';
$string['thinking'] = 'חושב…';
$string['tutornotenabled'] = 'מורה ה-AI אינו מופעל עבור קורס זה.';
$string['unknownerror'] = 'שגיאה לא ידועה';
$string['widgetposition'] = 'מיקום הווידג\'ט';
$string['widgetposition_desc'] = 'לאיזו פינה בעמוד הקורס יוצמד ווידג\'ט הצ\'אט.';
