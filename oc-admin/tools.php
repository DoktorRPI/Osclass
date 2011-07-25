<?php if ( ! defined('ABS_PATH')) exit('ABS_PATH is not loaded. Direct access is not allowed.');

    /*
     *      OSCLass – software for creating and publishing online classified
     *                           advertising platforms
     *
     *                        Copyright (C) 2010 OSCLASS
     *
     *       This program is free software: you can redistribute it and/or
     *     modify it under the terms of the GNU Affero General Public License
     *     as published by the Free Software Foundation, either version 3 of
     *            the License, or (at your option) any later version.
     *
     *     This program is distributed in the hope that it will be useful, but
     *         WITHOUT ANY WARRANTY; without even the implied warranty of
     *        MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
     *             GNU Affero General Public License for more details.
     *
     *      You should have received a copy of the GNU Affero General Public
     * License along with this program.  If not, see <http://www.gnu.org/licenses/>.
     */

    class CAdminTools extends AdminSecBaseModel
    {
        function __construct() {
            parent::__construct() ;
        }

        //Business Layer...
        function doModel() {

            switch ($this->action) {
                case 'import':          // calling import view
                                        $this->doView('tools/import.php');
                break;
                case 'import_post':     // calling
                                        $sql = Params::getFiles('sql') ;
                                        if(isset($sql['size']) && $sql['size']!=0) {
                                            //dev.conquer: if the file es too big, we can have problems with the upload or with memory
                                            $content_file = file_get_contents($sql['tmp_name']) ;

                                            $conn = getConnection() ;
                                            if ( $conn->osc_dbImportSQL($content_file) ) {
                                                osc_add_flash_ok_message( _m('Import complete'), 'admin') ;
                                            } else {
                                                osc_add_flash_error_message( _m('There was a problem importing data to the database'), 'admin') ;
                                            }
                                        } else {
                                                osc_add_flash_error_message( _m('No file was uploaded'), 'admin') ;
                                        }
                                        $this->redirectTo(osc_admin_base_url(true) . '?page=tools&action=import') ;
                break;
                case 'images':          // calling images view
                                        $this->doView('tools/images.php') ;
                break;
                case 'images_post':
                                        $preferences = Preference::newInstance()->toArray() ;

                                        $wat = new Watermark();
                                        $aResources = ItemResource::newInstance()->getAllResources();
                                        foreach($aResources as $resource) {
                                            $path = osc_content_path() . 'uploads/' ;
                                            // comprobar que no haya original
                                            $img_original = $path . $resource['pk_i_id']. "_original*";
                                            $aImages = glob($img_original);
                                            // there is original image
                                            if( count($aImages) == 1 ) {
                                                $image_tmp = $aImages[0] ;
                                            } else {
                                                $img_thumbnail = $path . $resource['pk_i_id']. "_thumbnail*" ;
                                                $aImages = glob( $img_thumbnail );
                                                $image_tmp = $aImages[0] ;
                                            }
                                            
                                            // extension
                                            preg_match('/\.(.*)$/', $image_tmp, $matches) ;
                                            if( isset($matches[1]) ) {
                                                $extension = $matches[1] ;

                                                // Create thumbnail
                                                $path = osc_content_path(). 'uploads/' . $resource['pk_i_id'] . '_thumbnail.jpg' ;
                                                $size = explode('x', osc_thumbnail_dimensions()) ;
                                                ImageResizer::fromFile($image_tmp)->resizeTo($size[0], $size[1])->saveToFile($path) ;

                                                if( osc_is_watermark_text() ) {
                                                    $wat->doWatermarkText( $path , osc_watermark_text_color(), osc_watermark_text() , 'image/jpeg');
                                                } elseif ( osc_is_watermark_image() ){
                                                    $wat->doWatermarkImage( $path, 'image/jpeg');
                                                }

                                                // Create normal size
                                                $path = osc_content_path() . 'uploads/' . $resource['pk_i_id'] . '.jpg' ;
                                                $size = explode('x', osc_normal_dimensions()) ;
                                                ImageResizer::fromFile($image_tmp)->resizeTo($size[0], $size[1])->saveToFile($path) ;

                                                if( osc_is_watermark_text() ) {
                                                    $wat->doWatermarkText( $path , osc_watermark_text_color(), osc_watermark_text() , 'image/jpeg' );
                                                } elseif ( osc_is_watermark_image() ){
                                                    $wat->doWatermarkImage( $path, 'image/jpeg');
                                                }

                                                // update resource info
                                                ItemResource::newInstance()->update(
                                                                        array(
                                                                            's_path'            => 'oc-content/uploads/'
                                                                            ,'s_name'           => osc_genRandomPassword()
                                                                            ,'s_extension'      => 'jpg'
                                                                            ,'s_content_type'   => 'image/jpeg'
                                                                        )
                                                                        ,array(
                                                                            'pk_i_id'       => $resource['pk_i_id']
                                                                        )
                                                ) ;

                                                // si extension es direfente a jpg, eliminar las imagenes con $extension si hay
                                                if( $extension != 'jpg' ) {
                                                    $files_to_remove = osc_content_path(). 'uploads/' . $resource['pk_i_id'] . "*" . $extension;
                                                    array_map( "unlink", glob( $files_to_remove ) );
                                                }
                                                // ....
                                            } else {
                                                // no es imagen o imagen sin extesión
                                            }
                                            
                                        }

                                        osc_add_flash_ok_message( _m('Re-generation complete'), 'admin') ;
                                        $this->redirectTo(osc_admin_base_url(true) . '?page=tools&action=images') ;
                break;
                case 'upgrade':
                                        $this->doView('tools/upgrade.php') ;
                break;
                case 'backup':
                                        $this->doView('tools/backup.php') ;
                break;
                case 'backup-sql':      //databasse dump...
                                        if( Params::getParam('bck_dir') != '' ) {
                                            $path = trim( Params::getParam('bck_dir') ) ;
                                            if(substr($path, -1, 1) != "/") {
                                                 $path .= '/' ;
                                            }
                                        } else {
                                            $path = osc_base_path() ;
                                        }
                                        $filename = 'OSClass_mysqlbackup.' . date('YmdHis') . '.sql' ;

                                        switch ( osc_dbdump($path, $filename) ) {
                                            case(-1):   $msg = _m('Path is empty') ;
                                                        osc_add_flash_error_message( $msg, 'admin') ;
                                            break;
                                            case(-2):   $msg = sprintf(_m('Could not connect with the database. Error: %s'), mysql_error()) ;
                                                        osc_add_flash_error_message( $msg, 'admin') ;
                                            break;
                                            case(-3):   $msg = sprintf(_m('Could not select the database. Error: %s'), mysql_error()) ;
                                                        osc_add_flash_error_message( $msg, 'admin') ;
                                            break;
                                            case(-4):   $msg = _m('There are no tables to back up') ;
                                                        osc_add_flash_error_message( $msg, 'admin') ;
                                            break;
                                            case(-5):   $msg = _m('The folder is not writable') ;
                                                        osc_add_flash_error_message( $msg, 'admin') ;
                                            break;
                                            default:    $msg = _m('Backup has been done properly') ;
                                                        osc_add_flash_ok_message( $msg, 'admin') ;
                                            break;
                                        }
                                        $this->redirectTo( osc_admin_base_url(true) . '?page=tools&action=backup' ) ;
                break;
                case 'backup-zip':      //zip of the code just to back it up
                                        if( Params::getParam('bck_dir') != '' ) {
                                            $archive_name = trim( Params::getParam('bck_dir') ) ;
                                            if(substr(trim($archive_name), -1, 1) != "/") {
                                                 $archive_name .= '/' ;
                                            }
                                            $archive_name = Params::getParam('bck_dir') . '/OSClass_backup.' . date('YmdHis') . '.zip' ;
                                        } else {
                                            $archive_name = osc_base_path() . "OSClass_backup." . date('YmdHis') . ".zip" ;
                                        }
                                        $archive_folder = osc_base_path() ;

                                        if ( osc_zip_folder($archive_folder, $archive_name) ) {
                                            $msg = _m('Archiving successful!') ;
                                            osc_add_flash_ok_message( $msg, 'admin') ;
                                        }else{
                                            $msg = _m('Error, the zip file was not created at the specified directory') ;
                                            osc_add_flash_error_message( $msg, 'admin') ;
                                        }
                                        $this->redirectTo( osc_admin_base_url(true) . '?page=tools&action=backup' ) ;
                break;
                case 'backup_post':
                                        $this->doView('tools/backup.php');
                break;
                case 'maintenance':
                                        $mode = Params::getParam('mode');
                                        if($mode=='on') {
                                            $maintenance_file = ABS_PATH . '.maintenance';
                                            $fileHandler = @fopen($maintenance_file, 'w');
                                            if($fileHandler) {
                                                osc_add_flash_ok_message( _m('Maintenance mode is ON'), 'admin') ;
                                            } else {
                                                osc_add_flash_error_message( _m('There was an error creating .maintenance file, please create it manually at the root folder'), 'admin') ;
                                            }
                                            fclose($fileHandler);
                                            $this->redirectTo( osc_admin_base_url(true) . '?page=tools&action=maintenance' ) ;
                                        } else if($mode=='off') {
                                            $deleted = @unlink(ABS_PATH . '.maintenance');
                                            if($deleted) {
                                                osc_add_flash_ok_message( _m('Maintenance mode is OFF'), 'admin') ;
                                            } else {
                                                osc_add_flash_error_message( _m('There was an error removing .maintenance file, please remove it manually from the root folder'), 'admin') ;
                                            }
                                            $this->redirectTo( osc_admin_base_url(true) . '?page=tools&action=maintenance' ) ;
                                        }
                                        $this->doView('tools/maintenance.php');
                break;
                default:
            }
        }

        //hopefully generic...
        function doView($file) {
            osc_current_admin_theme_path($file) ;
            Session::newInstance()->_clearVariables();
        }
    }

?>