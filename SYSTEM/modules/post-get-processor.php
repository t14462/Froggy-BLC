<?php

if(!defined('SECURE_ACCESS')) { die('Direct access not permitted'); }

################################################
################################################
################################################

require_once "SYSTEM/modules/action-post.php";
require_once "SYSTEM/modules/action-get.php";




function detectaction(){

    global $safeGet;
    global $safePost;


    if(
        // Редактирование и админ-действия
        isset($safeGet['edit']) ||
        isset($safeGet['cmove']) ||
        isset($safeGet['addpage']) ||
        /* isset($safePost['title'], $safePost['h'], $safePost['textedit']) || */
        
        // Управление страницами
        isset($safeGet['pagedel']) ||
        isset($safeGet['pgmoveup']) ||
        isset($safeGet['pgmovedown']) ||

        // Логи и администрирование
        isset($safeGet['log']) ||
        isset($safeGet['purgelog']) ||

        // Удаление файлов
        isset($safeGet['delimg']) ||
        isset($safeGet['delfile']) ||

        // Загрузка файлов
        isset($safePost['imgup']) ||
        isset($safePost['fuptrigger'])
    ) {
        return true;
    } else {
        return false;
    }
}



if(!$checkpermission && detectaction()) {

    $errmsg = pforbidden();

} elseif( /* $checkpermission && */ isset( /* $safePost["pageaddr"], */ $safePost["textedit"], $safePost["h"], $safePost["title"])) {

    savePage();

} elseif( /* $checkpermission && */ isset($safeGet["pagedel"])) {

    deletePage();

} elseif( /* $checkpermission && */ isset($safePost["imgup"])) {

    imageupload();

} elseif( /* $checkpermission && */ isset($safePost["fuptrigger"])) {

    fileDlUpload();

} elseif( /* $checkpermission && */ isset($safeGet["cmove"])) {

    commentRemove();

} elseif( /* $checkpermission && */ isset($safeGet["log"])) {

    viewLog();

} elseif( /* $checkpermission && */ isset($safeGet['edit'])) {

    pageEdit();

} elseif( /* $checkpermission && */ isset($safeGet["addpage"])) {

    addPage();

} elseif( /* $checkpermission && */ isset($safeGet["pgmoveup"])) {

    movePageUp();

} elseif( /* $checkpermission && */ isset($safeGet["pgmovedown"])) {

    movePageDown();

} elseif( /* $checkpermission && */ isset($safeGet["delimg"])) {

    delimg();

} elseif( /* $checkpermission && */ isset($safeGet["delfile"])) {

    delfile();

} elseif( /* $checkpermission && */ isset($safeGet["purgelog"])) {

    purgelog();


##################################################
##################################################
##################################################
##################################################


} elseif(isset($safeGet["permalink"])) {

    permalink();

} elseif(isset($safeGet["print"]) && $ispageexist) {

    $printpgvar = "-print";
    pageload();

} elseif(isset($safePost["registerp"], $safePost["rusername"], $safePost["rpassword1"], $safePost["rpassword2"])) {

    registerp();

} elseif(isset($safeGet["registerg"])) {

    registerg();

} elseif(isset($safeGet["nredir"])) {

    nredir();

} elseif(isset($safePost["selected_template"])) {

    saveTplSess();

} elseif(isset($safePost["commpost"], $safePost["commaddr"], $safePost["pgcommnum"], $safePost["repcommid"], $safePost["visitor"], $safePost["captcha"], $safePost["commpage"])) {

    commentReply();

} elseif(isset($safePost["commpost"], $safePost["commaddr"], $safePost["visitor"], $safePost["captcha"])) {

    postComment();

} elseif(isset($safePost["username"], $safePost["password"])) {

    loginPost();

} elseif(isset($safeGet["gallery"])) {

    gallery();

} elseif(isset($safeGet["dlfiles"])) {

    dlFiles();

} elseif(isset($safeGet["logout"])) {

    logout();

} elseif(isset($safeGet["login"])) {

    loginPage();

} else {

    pageload();
}
