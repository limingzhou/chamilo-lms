<?php
/* For licensing terms, see /license.txt */
/**
* Code for Aiken import integration.
* @package chamilo.exercise
* @author Ronny Velasquez <ronny.velasquez@beeznest.com>
* @author César Perales <cesar.perales@gmail.com> Updated function names and import files for Aiken format support
*/
/**
 * Code
 */
// name of the language file that needs to be included
$language_file = 'exercice';

// including the global Chamilo file
require_once '../inc/global.inc.php';
require_once api_get_path(LIBRARY_PATH).'document.lib.php';

// including additional libraries
require_once 'exercise.class.php';
require_once 'question.class.php';
require_once 'answer.class.php';
require_once 'unique_answer.class.php';


// section (for the tabs)
$this_section = SECTION_COURSES;

// access restriction: only teachers are allowed here
if (!api_is_allowed_to_edit(null, true)) {
    api_not_allowed();
}

// the breadcrumbs
$interbreadcrumb[]= array ("url"=>"exercice.php", "name"=> get_lang('Exercices'));
$is_allowedToEdit = api_is_allowed_to_edit(null, true);

/**
 * This function displays the form for import of the zip file with qti2
 */
function aiken_display_form($msg = '') {
    $name_tools = get_lang('ImportAikenQuiz');
    $form  = '<div class="actions">';
    $form .= '<a href="exercice.php?show=test">' . Display :: return_icon('back.png', get_lang('BackToExercisesList'),'',ICON_SIZE_MEDIUM).'</a>';
    $form .= '</div>';
    if (!empty($msg)) {
        $form .= $msg;
    }
    $form_validator  = new FormValidator('aiken_upload', 'post',api_get_self()."?".api_get_cidreq(), null, array('enctype' => 'multipart/form-data') );
    $form_validator->addElement('header', $name_tools);    
    $form_validator->addElement('file', 'userFile', get_lang('DownloadFile'));    
    $form_validator->addElement('style_submit_button', 'submit', get_lang('Send'), 'class="upload"');    
    $form .= $form_validator->return_form();    

    echo $form;
}

/**
 * This function will import the zip file with the respective qti2
 * @param array $uploaded_file ($_FILES)
 */
function aiken_import_file($array_file) {

    $unzip = 0;
    $lib_path = api_get_path(LIBRARY_PATH);
    require_once $lib_path.'fileUpload.lib.php';
    require_once $lib_path.'fileManage.lib.php';
    $process = process_uploaded_file($array_file);
    if (preg_match('/\.(zip|txt)$/i', $array_file['name'])) {
        // if it's a zip, allow zip upload
        $unzip = 1;
    }

    if ($process && $unzip == 1) {
        $main_path = api_get_path(SYS_CODE_PATH);
        require_once $main_path.'exercice/export/aiken/aiken_import.inc.php';
        require_once $main_path.'exercice/export/aiken/aiken_classes.php';
        $imported = import_exercise($array_file['name']);

        if ($imported) {
            header('Location: exercice.php?'.api_get_cidreq());
        } else {
            $msg = Display::return_message(get_lang('UplNoFileUploaded'),'error');
            return $msg;
        }
    }
}

$msg = '';
// import file
if ((api_is_allowed_to_edit(null, true))) {
    if (isset($_POST['submit'])) {
        $msg = aiken_import_file($_FILES['userFile']);
    }
}

// display header
Display::display_header(get_lang('ImportAikenQuiz'), 'Exercises');

// display Aiken form
aiken_display_form($msg);

// display the footer
Display::display_footer();