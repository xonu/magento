<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * @category    Mage
 * @package     Mage_Core
 * @copyright   Copyright (c) 2004-2007 Irubin Consulting Inc. DBA Varien (http://www.varien.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Proxy script to combine and compress one or few files for JS and CSS
 *
 * Restricts access only to files under current script's folder
 *
 * @category    Mage
 * @package     Mage_Core
 */

// no files specified return 404
if (empty($_GET['f'])) {
    header('404 Not found');
    echo "SYNTAX: proxy.php/x.js?f=dir1/file1.js,dir2/file2.js";
    exit;
}

// allow web server set content type automatically
$contentType = false;

// set custom content type if specified
if (isset($_GET['c'])) {
    $contentType = $_GET['c']==='auto' ? true : $_GET['c'];
}

// get files content
$files = is_array($_GET['f']) ? $_GET['f'] : explode(',', $_GET['f']);

$out = '';
foreach ($files as $f) {
    // get correct file disk path
    $p = trim(str_replace('/', DIRECTORY_SEPARATOR, $f), DIRECTORY_SEPARATOR);

    // validate file path
    if (empty($p) || strpos($p, '..')!==false || strpos($p, '//')!==false || !file_exists($p)) {
        continue;
    }
    // try automatically get content type if requested
    if ($contentType===true) {
        $contentTypes = array(
            'js' => 'text/javascript',
//            'css' => 'text/css',
//            'gif' => 'image/gif',
//            'png' => 'image/png',
//            'jpg' => 'image/jpeg',
        );
        $ext = strtolower(pathinfo($p, PATHINFO_EXTENSION));
        if (empty($contentTypes[$ext])) { // security
            continue;
        }
        $contentType = !empty($contentTypes[$ext]) ? $contentTypes[$ext] : false;
    }

    // append file contents
    $out .= file_get_contents($p);
}

// optional custom content type, can be emulated by proxy.php/x.js or x.css
if (is_string($contentType)) {
    header('Content-type: '.$contentType);
}

// remove spaces, default on
if (!(isset($_GET['s']) && !$_GET['s'])) {
    $out = preg_replace('#[ \t]+#', ' ', $out);
}

// use gzip or deflate, use this if not enabled in .htaccess, default on
if (!(isset($_GET['z']) && !$_GET['z'])) {
    ini_set('zlib.output_compression', 1);
}

// add Expires header if not disabled, default 1 year
if (!(isset($_GET['e']) && $_GET['e']==='no')) {
    $time = time()+(isset($_GET['e']) ? $_GET['e'] : 365)*86400;
    header('Expires: '.gmdate('r', $time));
}

echo $out;
