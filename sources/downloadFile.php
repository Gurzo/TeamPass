<?php
/**
 * Teampass - a collaborative passwords manager.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * @category  Teampass
 *
 * @author    Nils Laumaillé <nils@teampass.net>
 * @copyright 2009-2019 Nils Laumaillé
* @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
*
 * @version   GIT: <git_id>
 *
 * @see      http://www.teampass.net
 */
require_once 'SecureHandler.php';
session_name('teampass_session');
session_start();
if (isset($_SESSION['CPM']) === false
    || $_SESSION['CPM'] != 1
    || isset($_SESSION['user_id']) === false || empty($_SESSION['user_id'])
    || isset($_SESSION['key']) === false || empty($_SESSION['key'])
) {
    die('Hacking attempt...');
}

// Load config if $SETTINGS not defined
if (isset($SETTINGS['cpassman_dir']) === false || empty($SETTINGS['cpassman_dir'])) {
    if (file_exists('../includes/config/tp.config.php')) {
        include_once '../includes/config/tp.config.php';
    } elseif (file_exists('./includes/config/tp.config.php')) {
        include_once './includes/config/tp.config.php';
    } elseif (file_exists('../../includes/config/tp.config.php')) {
        include_once '../../includes/config/tp.config.php';
    } else {
        throw new Exception("Error file '/includes/config/tp.config.php' not exists", 1);
    }
}

// Include files
require_once $SETTINGS['cpassman_dir'].'/includes/libraries/protect/SuperGlobal/SuperGlobal.php';
$superGlobal = new protect\SuperGlobal\SuperGlobal();

// Prepare GET variables
$get_filename = $superGlobal->get('name', 'GET');
$get_fileid = $superGlobal->get('fileid', 'GET');

// prepare Encryption class calls
use Defuse\Crypto\File;

header('Content-disposition: attachment; filename='.rawurldecode(basename($get_filename)));
header('Content-Type: application/octet-stream');
header('Cache-Control: must-revalidate, no-cache, no-store');
header('Expires: 0');
if (isset($_GET['pathIsFiles']) && $_GET['pathIsFiles'] == 1) {
    readfile($SETTINGS['path_to_files_folder'].'/'.basename($get_filename));
} else {
    include_once 'main.functions.php';

    // connect to the server
    include_once $SETTINGS['cpassman_dir'].'/includes/libraries/Database/Meekrodb/db.class.php';
    DB::$host = DB_HOST;
    DB::$user = DB_USER;
    DB::$password = defuseReturnDecrypted(DB_PASSWD, $SETTINGS);
    DB::$dbName = DB_NAME;
    DB::$port = DB_PORT;
    DB::$encoding = DB_ENCODING;
    $link = mysqli_connect(DB_HOST, DB_USER, defuseReturnDecrypted(DB_PASSWD, $SETTINGS), DB_NAME, DB_PORT);
    $link->set_charset(DB_ENCODING);

    // get file key
    $file_info = DB::queryfirstrow(
        'SELECT f.id AS id, f.file AS file, f.name AS name, f.status AS status, f.extension AS extension,
        s.share_key AS share_key
        FROM '.prefixTable('files').' AS f
        INNER JOIN '.prefixTable('sharekeys_files').' AS s ON (f.id = s.object_id)
        WHERE s.user_id = %i AND s.object_id = %i',
        $_SESSION['user_id'],
        $get_fileid
    );

    // Decrypt the file
    $fileContent = decryptFile(
        $file_info['file'],
        $SETTINGS['path_to_upload_folder'],
        decryptUserObjectKey($file_info['share_key'], $_SESSION['user']['private_key'])
    );

    // Set the filename of the download
    $filename = base64_decode(basename($file_info['name'], $file_info['extension']));

    // Output CSV-specific headers
    header('Pragma: public');
    header('Expires: 0');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Cache-Control: private', false);
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="'.$filename.'.'.$file_info['extension'].'";');
    header('Content-Transfer-Encoding: binary');

    // Stream the CSV data
    exit(base64_decode($fileContent));
}
