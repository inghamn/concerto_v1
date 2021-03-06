<?php
/**
 * This file was developed as part of the Concerto digital signage project
 * at RPI.
 *
 * Copyright (C) 2009 Rensselaer Polytechnic Institute
 * (Student Senate Web Technologies Group)
 *
 * This program is free software; you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by the Free
 * Software Foundation; either version 2 of the License, or (at your option)
 * any later version.
 *
 * This program is distributed in the hope that it will be useful, but
 * WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * General Public License for more details.  You should have received a copy
 * of the GNU General Public License along with this program.
 *
 * @package      Concerto
 * @author       Web Technologies Group, $Author$
 * @copyright    Rensselaer Polytechnic Institute
 * @license      GPLv2, see www.gnu.org/licenses/gpl-2.0.html
 * @version      $Revision$
 */
function outputImageFile($file)
{
    $timestamp = filemtime($file);
    header("Last-Modified: ".gmdate("D, d M Y H:i:s", $timestamp)." GMT");

    $img = getimagesize($file);
    $fp = fopen($file, "rb");
    header('Content-type: ' .$img['mime']);
    header('Content-Length: ' .filesize($file));
    fpassthru($fp);
    exit();
}

function render($type, $filename, $width = false, $height = false, $stretch = false)
{
    $fileinfo = explode(".", $filename);
    if ($type == 'image') {
        $cache_path = IMAGE_DIR . 'cache/' . $fileinfo[0] . '_' . $width . '_' . $height . '.' . $fileinfo[1];
        $path = IMAGE_DIR . $filename;
    } elseif ($type == 'template'){
        $cache_path = TEMPLATE_DIR . 'cache/' . $fileinfo[0] . '_' . $width . '_' . $height . '.' . $fileinfo[1];
        $path = TEMPLATE_DIR . $filename;
    }


    //send not modified headers if the image has not been modified since
    if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) >= $timestamp) {
        header('HTTP/1.0 304 Not Modified');
        return;
    }

    //lets render the image!
    if ($width && $height) {
        //Do we already have a cached copy at the ready?
        if (file_exists($cache_path)) {
            outputImageFile($cache_path);
        }
        else {
            // Generate a new cache copy
            $dimensions = $width.'x'.$height;
            exec(IMAGEMAGICK."/convert $path -channel rgba -alpha set -resize '$dimensions>' $cache_path");

            $LOG = fopen(CONTENT_DIR . 'render/render_log', 'a');
            fwrite($LOG, "$path $width $height\n");
            fclose($LOG);

            outputImageFile($cache_path);
        }
    }
    else {
        outputImageFile($path);
    }
}

function cache_parse($threshold = 100)
{
    $log_file = CONTENT_DIR . 'render/render_log';
    if ($fh = @fopen($log_file, 'r')) {
        while (!feof($fh)) {
            $line = fgets($fh);
            $line = str_replace("\n", "", $line);
            $line_data = split(' ', $line);
            if (count($line_data) == 3) {
                if (isset($data[$line_data[0]][$line_data[1]][$line_data[2]])) {
                    $data[$line_data[0]][$line_data[1]][$line_data[2]]++; //Count how many times that content/width/height set apperas
                } else {
                    $data[$line_data[0]][$line_data[1]][$line_data[2]] = 1;
                }
            }
        }
        fclose($fh);
        unset($fh);
    }
    if (!isset($data)) { //Nothing has been viewed/logged.  Do no more thinking
        return true;
    }
    //Now we find those entries that are worthy of a cache
    foreach ($data as $file_path => $details) {
        foreach ($details as $width => $h_details) {
            foreach ($h_details as $height => $count) {
                if ($count > $threshold) { // The content has been called enough we should cache it
                    generate_cache($file_path, $width, $height);
                }
            }
        }
    }
    //Then empty the log file
    @system("rm $log_file");
}

function generate_cache($filename, $width, $height)
{
    $path = explode('/', $filename);

    $file = end($path);
    $fileinfo = explode("\.", $file);
    $type = $path[count($path)-2];

    if ($type == 'images') {
        $cache_path = IMAGE_DIR . 'cache/' . $fileinfo[0] . '_' . $width . '_' . $height . '.' . $fileinfo[1];
    }
    elseif ($type == 'templates') {
        $cache_path = TEMPLATE_DIR . 'cache/' . $fileinfo[0] . '_' . $width . '_' . $height . '.' . $fileinfo[1];
    }

    //Maybe we already got it and the log it out of date
    if (!file_exists($cache_path)) {
        echo 'Handling ' . $types . ' -- ' . $file . "\n";
        $new_width  = $width;
        $new_height = $height;

        list($old_width, $old_height) = getimagesize($filename);
        $old_ratio = $old_width / $old_height;
        $new_ratio = $new_width / $new_height;

        if ($old_ratio < $new_ratio) {
            $new_height = $new_height;
            $new_width  = $new_height * $old_ratio;
        }
        else {
            $new_width  = $new_width;
            $new_height = $new_width / $old_ratio;
        }

        $new_image = imagecreatetruecolor($new_width, $new_height);

        $image = @imagecreatefromjpeg($filename) or //Read JPEG
        $image = @imagecreatefrompng($filename) or //Read PNG
        $image = @imagecreatefromgif($filename) or //Read GIF
        $image = false;

        $cache_path_tmp = $cache_path . '.tmp'; //Store the cache in a working file until we know how the resize went

        if ($image) {
            imagecopyresampled($new_image, $image, 0, 0, 0, 0, $new_width, $new_height, $old_width, $old_height);
            imagedestroy($image);
            if ($fileinfo[1] == 'jpg' || $fileinfo[1] == 'jpeg') {
                $ret = imagejpeg($new_image, $cache_path_tmp, 80);
            }
            elseif ($fileinfo[1] == 'png') {
                $ret = imagepng($new_image, $cache_path_tmp, 1);
            }
            elseif ($fileinfo[1] == 'gif') {
                $ret = imagegif($new_image, $cache_path_tmp);
            }

            //The file started to get generated but hit an error
            if (!$ret && file_exists($cache_path_tmp)) {
                unlink($cache_path_tmp);
            }
            else {
                //The resize went ok, so we'll move it to its normal home
                rename($cache_path_tmp, $cache_path);
            }
            imagedestroy($new_image);
        }
        echo "Ok.\n";
    }
}

function clear_cache($dirname)
{
    $dir = opendir($dirname);
    while ($file = readdir($dir)) {
        if (($file != ".") && ($file != "..") && !is_dir($dirname . $file)) {
            unlink($dirname . $file);
        }
    }
    closedir($dir);
}
