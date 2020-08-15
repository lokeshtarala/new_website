<?php

class arsamplecontroller {

    function __construct() {
        add_action('admin_menu', array($this, 'arf_sample_menu'), 26);
        add_action('wp_ajax_arf_sample_download_forms', array($this, 'arf_sample_download_forms'));
    }
    function arf_sample_menu() {

        add_submenu_page('ARForms', 'ARForms | ' . addslashes(esc_html__('Install Samples', 'ARForms')), addslashes(esc_html__('Install Samples', 'ARForms')), 'arfviewforms', 'ARForms-samples', array($this, 'arf_sample_route'));        
    }

    function arf_sample_route() {

        global $arsamplecontroller;
        if (isset($_REQUEST['page']) && $_REQUEST['page'] == 'ARForms-samples') {
            if (file_exists(VIEWS_PATH . '/samples_lists.php')) {
                include( VIEWS_PATH . '/samples_lists.php' );
            }
        } 
    }
    function samples_list(){
        
        global $arsamplecontroller, $arfversion, $MdlDb, $arnotifymodel, $arfform, $arfrecordmeta;

        $bloginformation = array();
        $str = $MdlDb->get_rand_alphanumeric(10);

        if (is_multisite())
            $multisiteenv = "Multi Site";
        else
            $multisiteenv = "Single Site";

        $addon_listing = 1;

        $bloginformation[] = $arnotifymodel->sitename();
        $bloginformation[] = $arfform->sitedesc();
        $bloginformation[] = home_url();
        $bloginformation[] = get_bloginfo('admin_email');
        $bloginformation[] = $arfrecordmeta->wpversioninfo();
        $bloginformation[] = $arfrecordmeta->getlanguage();
        $bloginformation[] = $arfversion;
        $bloginformation[] = $_SERVER['REMOTE_ADDR'];
        $bloginformation[] = $str;
        $bloginformation[] = $multisiteenv;
        $bloginformation[] = $addon_listing;

        $valstring = implode("||", $bloginformation);
        $encodedval = base64_encode($valstring);

        $urltosample = 'https://www.arformsplugin.com/download_samples/arf_sample_list.php';
        //$urltosample = 'http://192.168.0.36/mitul/wp/plugin_demos/arforms/download_samples/arf_sample_list.php';

        $raw_response = wp_remote_post($urltosample, array(
            'method' => 'POST',
            'timeout' => 45,
            'redirection' => 5,
            'httpversion' => '1.0',
            'blocking' => true,
            'headers' => array(),
            'body' => array('wpversion' => $encodedval, 'user_agent' => $_SERVER['HTTP_USER_AGENT']),
            'cookies' => array()
        ));

        if (is_wp_error($raw_response) || $raw_response['response']['code'] != 200) {
            echo "<div class='error_message' style='margin-top:100px; padding:20px;'>" . addslashes(esc_html__("Forms listing is currently unavailable. Please try again later.", "ARForms")) . "</div>";
        } else {
            echo $arsamplecontroller->arf_sample_display_forms($raw_response['body']);
        }
    }

    function arf_check_valid_sample(){
        global $check_valid_sample,$arformcontroller;

        $setlicval = $arformcontroller->$check_valid_sample();

        return $setlicval;
    }

    function arf_sample_display_forms($arf_samples = '') {

        require(VIEWS_PATH . '/arf_view_samples.php');

    }
    function arf_sample_download_forms(){
        global $arsamplecontroller;
        if(isset($_POST['form_id']) && $_POST['form_id'] !=''){

            $is_valid_sample = $this->arf_check_valid_sample();

            if( $is_valid_sample != 1 ){
                $return['error'] = true;
                $return['error_type'] = 'license_error';
                $return['message'] = esc_html__('Please activate license to install the templates.', ARP_PT_TXTDOMAIN);

                echo json_encode($return);
                exit;
            }

            $arf_pcode = "";

            $get_purchased_info = get_option('arfSortInfo');
            $sortorderval = base64_decode($get_purchased_info);
            $ordering = explode("^", $sortorderval);
            if(is_array($ordering)) {
                $arf_pcode = (isset($ordering[0]) && $ordering[0]!= "") ? $ordering[0] : "";
            }

            $arf_post_url = "https://www.arformsplugin.com/download_samples/arf_download_samples_forms.php";
            //$arf_post_url = "http://192.168.0.36/mitul/wp/plugin_demos/arforms/download_samples/arf_download_samples_forms.php";

            $user_agent = $_SERVER['HTTP_USER_AGENT'];

            $raw_response = wp_remote_post($arf_post_url, array(
                'method' => 'POST',
                'timeout' => 45,
                'redirection' => 5,
                'httpversion' => '1.0',
                'blocking' => true,
                'headers' => array(),
                'body' => array('form_id' => $_POST['form_id'],'arf_pcode'=>$arf_pcode, 'user_agent' => $user_agent  ),
                'cookies' => array()
            ));

            if (is_wp_error($raw_response) || $raw_response['response']['code'] != 200) {
                echo addslashes(esc_html__("Form currently unavailable. Please try again later.", "ARForms"));
            } else {
                $arf_error_type = $arf_error_msg = "";
                $arf_error = base64_decode($raw_response['body']);
                $arf_error_arr = explode("|~^~|", $arf_error);

                if (is_array($arf_error_arr) && count($arf_error_arr) > 0) {
                    $arf_error_type = (isset($arf_error_arr[0]) && $arf_error_arr[0] != "") ? $arf_error_arr[0] : "";
                    $arf_error_msg = (isset($arf_error_arr[1]) && $arf_error_arr[1] != "") ? $arf_error_arr[1] : "";
                }
                if( $arf_error_type != "" && in_array( $arf_error_type, array('empty_pcode','invalid_pcode','lic_expired','exceed_limit') ) ) {
                    $return['error'] = true;
                    $return['error_type'] = 'license_error';
                    $return['message'] = esc_html__($arf_error_msg, ARP_PT_TXTDOMAIN);
                } else {
                    $arf_import = $arsamplecontroller->arf_import_sample_form($raw_response['body']);
                    if($arf_import > 0){
                        $return['error'] = false;
                        $return['new_id'] = $arf_import;
                        $return['message'] = esc_html__('Sample template is installed successfully.', ARP_PT_TXTDOMAIN);
                    } else {
                        $return['error'] = true;
                        $return['error_type'] = '';
                        $return['message'] = esc_html__('Something went wrong, while installing sample template. Please try again', ARP_PT_TXTDOMAIN);
                    }
                }
                echo json_encode($return);
                exit;
            }
        }

        exit;
    }

    function arf_import_sample_form($xmldata){
        
        global $current_user, $arformhelper,$arf_installed_field_types,$arrecordcontroller;
        $browser_info = $arrecordcontroller->getBrowser($_SERVER['HTTP_USER_AGENT']);

        @ini_set('max_execution_time', 0);
        
        $wp_upload_dir = wp_upload_dir();
        $upload_dir = $wp_upload_dir['basedir'] . '/arforms/css/';
        $main_css_dir = $wp_upload_dir['basedir'] . '/arforms/maincss/';
            $xml = html_entity_decode(base64_decode($xmldata));

            $outside_fields = apply_filters('arf_installed_fields_outside',$arf_installed_field_types);

            libxml_use_internal_errors(true);

            $xml = simplexml_load_string($xml);

            if( $xml === false ){
                $xml = base64_decode($_REQUEST['arf_import_textarea']);

                $outside_fields = apply_filters('arf_installed_fields_outside',$arf_installed_field_types);

                libxml_use_internal_errors(true);

                $xml = simplexml_load_string($xml);
            }
            
            $f1 = fopen("import_export_log.txt", "w");
            $errors = "";
            if ($xml === false) {
                $errors .= "Failed loading XML \n";
                foreach (libxml_get_errors() as $error) {
                    $errors .= "\n\t" . $error->message . "\n";
                }
            }
            fwrite($f1, $errors);
            fclose($f1);
            global $arffield, $arfform, $MdlDb, $wpdb, $WP_Filesystem, $armainhelper, $arfieldhelper, $arformhelper, $arsettingcontroller, $arfrecordmeta, $db_record, $arfsettings;
            if (isset($xml->form)) {
                $ik = 0;
                foreach ($xml->children() as $key_main => $val_main) {
                    $attr = $val_main->attributes();
                    $old_id = $attr['id'];
                    $submit_bg_img_fnm = '';
                    $arfmainform_bg_img_fnm = '';
                    $arfmainform_bg_hover_img_fnm = '';

                    $submit_bg_img = trim($val_main->submit_bg_img);
                    $arfmainform_bg_img = trim($val_main->arfmainform_bg_img);
                    $submit_hover_bg_img = trim($val_main->submit_hover_bg_img);
                    $xml_arf_version = trim($val_main->arf_db_version);
                    $exported_site_uploads_dir = trim($val_main->exported_site_uploads_dir);
                    $wp_upload_dir = wp_upload_dir();

                    $imageupload_dir = $wp_upload_dir['basedir'] . '/arforms/';

                    $imageupload_url = $wp_upload_dir['baseurl'] . '/arforms/';

                    //code start here for submit bg image
                    if ($submit_bg_img != '') {
                        $submit_bg_img_filenm = basename($submit_bg_img);

                        $submit_bg_img_fnm = time() . '_' . $ik . "_" . $submit_bg_img_filenm;
                        $ik++;

                        if (!@copy($submit_bg_img, $imageupload_dir . $submit_bg_img_fnm))
                            $submit_bg_img_fnm = '';
                    }
                    //code end here
                    //code start here for background bg image
                    if ($arfmainform_bg_img != '') {
                        $arfmainform_bg_img_filenm = basename($arfmainform_bg_img);

                        $arfmainform_bg_img_fnm = time() . '_' . $ik . "_" . $arfmainform_bg_img_filenm;
                        $ik++;

                        if (!@copy($arfmainform_bg_img, $imageupload_dir . $arfmainform_bg_img_fnm)) {
                            $arfmainform_bg_img_fnm = '';
                        }
                    }
                    if ($submit_hover_bg_img != '') {
                        $submit_hover_bg_img_filenm = basename($submit_hover_bg_img);


                        $arfmainform_bg_hover_img_fnm = time() . '_' . $ik . "_" . $submit_hover_bg_img_filenm;
                        $ik++;

                        if (!@copy($submit_hover_bg_img, $imageupload_dir . $arfmainform_bg_hover_img_fnm)) {
                            $arfmainform_bg_hover_img_fnm = '';
                        }
                    }
                    //code end here
                    //code start here for get all general options.
                    $val = '';
                    $old_field_orders = $new_field_order = array();
                    foreach ($val_main->general_options->children() as $key => $val) {
                        if ($key == 'options') {
                            $options_arr = '';
                            $options_key = '';
                            $options_val = '';
                            unset($option_arr_new);
                            $option_string = '';


                            

                            $options_arr = @unserialize($val);
                            
                            if( !is_array($options_arr) ){
                                $options_arr = json_decode($options_arr,true);
                            }



                            foreach ($options_arr as $options_key => $options_val) {
                                if (!is_array($options_val)) {
                                    $options_val = str_replace('[ENTERKEY]', '<br>', $options_val);
                                    $options_val = str_replace('[AND]', '&', $options_val);
                                }

                                if ($options_key == 'before_html') {
                                    $option_arr_new[$options_key] = $arformhelper->get_default_html('before');
                                } elseif ($options_key == 'ar_email_subject') {
                                    $_SESSION['ar_email_subject_org'] = $options_val;
                                    $option_arr_new[$options_key] = $options_val;
                                } elseif ($options_key == 'ar_email_message') {
                                    $_SESSION['ar_email_message_org'] = $options_val;
                                    $option_arr_new[$options_key] = $options_val;
                                } elseif ($options_key == 'ar_admin_email_message') {
                                    $_SESSION['ar_admin_email_message_org'] = $options_val;
                                    $option_arr_new[$options_key] = $options_val;
                                } elseif ($options_key == 'ar_email_to') {
                                    $_SESSION['ar_email_to_org'] = $options_val;
                                    $option_arr_new[$options_key] = $options_val;
                                } elseif ($options_key == 'ar_admin_from_email') {
                                    $_SESSION['ar_admin_from_email'] = $options_val;
                                    $option_arr_new[$options_key] = $options_val;
                                } elseif ($options_key == 'ar_user_from_email') {
                                    $_SESSION['ar_user_from_email'] = $options_val;
                                    $option_arr_new[$options_key] = $options_val;
                                } elseif ($options_key == 'arf_conditional_mail_rules') {
                                    $_SESSION['arf_conditional_mail_rules'] = $options_val;
                                    $option_arr_new[$options_key] = $options_val;
                                } elseif ($options_key == 'ar_admin_from_name') {
                                    $_SESSION['arf_admin_from_name'] = $options_val;
                                    $option_arr_new[$options_key] = $options_val;
                                } elseif ($options_key == 'admin_email_subject') {
                                    $_SESSION['admin_email_subject'] = $options_val;
                                    $option_arr_new[$options_key] = $options_val;
                                } elseif ($options_key == 'reply_to') {
                                    $_SESSION['reply_to'] = $options_val;
                                    $option_arr_new[$options_key] = $options_val;
                                } elseif ($options_key == 'arf_pre_dup_field') {
                                    $_SESSION['arf_pre_dup_field'] = $options_val;
                                    $option_arr_new[$options_key] = $options_val;
                                } else if($options_key == 'arf_field_order' ){
                                    $old_field_orders = json_decode($options_val,true);
                                    $option_arr_new[$options_key] = $options_val;
                                } else if($options_key == 'arf_field_resize_width' ){
                                    $option_arr_new[$options_key] = $options_val;
                                } else {
                                    $option_arr_new[$options_key] = $options_val;
                                }
                            }                                        
                            $option_string = @serialize($option_arr_new);

                            $general_option[$key] = $option_string;

                            $general_op = $option_string;
                        } elseif ($key == 'form_css') {
                            $form_css_arr = @maybe_unserialize(trim($val));

                            if (!isset($form_css_arr['prefix_suffix_bg_color']) || $form_css_arr['prefix_suffix_bg_color'] == '')
                                $form_css_arr['prefix_suffix_bg_color'] = '#e7e8ec';

                            if (!isset($form_css_arr['prefix_suffix_icon_color']) || $form_css_arr['prefix_suffix_icon_color'] == '')
                                $form_css_arr['prefix_suffix_icon_color'] = '#808080';

                            if (!isset($form_css_arr['arfsectionpaddingsetting_1']) || $form_css_arr['arfsectionpaddingsetting_1'] == '')
                                $form_css_arr['arfsectionpaddingsetting_1'] = '15';

                            if (!isset($form_css_arr['arfsectionpaddingsetting_2']) || $form_css_arr['arfsectionpaddingsetting_2'] == '')
                                $form_css_arr['arfsectionpaddingsetting_2'] = '10';

                            if (!isset($form_css_arr['arfsectionpaddingsetting_3']) || $form_css_arr['arfsectionpaddingsetting_3'] == '')
                                $form_css_arr['arfsectionpaddingsetting_3'] = '15';

                            if (!isset($form_css_arr['arfsectionpaddingsetting_4']) || $form_css_arr['arfsectionpaddingsetting_4'] == '')
                                $form_css_arr['arfsectionpaddingsetting_4'] = '10';



                            foreach ($form_css_arr as $form_css_key => $form_css_val) {
                                if ($form_css_key == 'submit_bg_img') {
                                    if ($submit_bg_img_fnm == '') {
                                        $form_css_arr_new['submit_bg_img'] = '';
                                        $form_css_arr_new_db['submit_bg_img'] = '';
                                    } else {


                                        $form_css_arr_new['submit_bg_img'] = $imageupload_url . $submit_bg_img_fnm;
                                        $form_css_arr_new_db['submit_bg_img'] = $imageupload_url . $submit_bg_img_fnm;
                                    }
                                } elseif ($form_css_key == 'arfmainform_bg_img') {
                                    if ($arfmainform_bg_img_fnm == '') {
                                        $form_css_arr_new[$form_css_key] = '';
                                        $form_css_arr_new_db[$form_css_key] = '';
                                    } else {

                                        $form_css_arr_new[$form_css_key] = $imageupload_url . $arfmainform_bg_img_fnm;
                                        $form_css_arr_new_db[$form_css_key] = $imageupload_url . $arfmainform_bg_img_fnm;
                                    }
                                } elseif ($form_css_key == 'submit_hover_bg_img') {
                                    if ($arfmainform_bg_hover_img_fnm == '') {
                                        $form_css_arr_new[$form_css_key] = '';
                                        $form_css_arr_new_db[$form_css_key] = '';
                                    } else {

                                        $form_css_arr_new[$form_css_key] = $imageupload_url . $arfmainform_bg_hover_img_fnm;
                                        $form_css_arr_new_db[$form_css_key] = $imageupload_url . $arfmainform_bg_hover_img_fnm;
                                    }
                                } elseif ($form_css_key == 'arf_checked_checkbox_icon' || $form_css_key == 'arf_checked_radio_icon') {
                                    $form_css_arr_new[$form_css_key] = $armainhelper->arf_update_fa_font_class($form_css_val);
                                    $form_css_arr_new_db[$form_css_key] = $armainhelper->arf_update_fa_font_class($form_css_val);
                                } else {
                                    $form_css_arr_new[$form_css_key] = $form_css_val;
                                    $form_css_arr_new_db[$form_css_key] = $form_css_val;
                                }

                            }

                            $final_val = @maybe_serialize($form_css_arr_new);
                            $final_val_db = @serialize($form_css_arr_new_db);
                            $general_option[$key] = $final_val;
                            $general_option[$key . '_db'] = $final_val_db;
                        } else {
                            $general_option[$key] = trim($val);
                        }
                    }
                    //code end here.                                
                    $general_option['is_importform'] = 'Yes';
                    //code start here for store all general options in database.
                    $autoresponder_fname = $general_option['autoresponder_fname'];
                    $autoresponder_lname = $general_option['autoresponder_lname'];
                    $autoresponder_email = $general_option['autoresponder_email'];

                    $general_option['form_key'] = '';
                    unset($general_option['id']);
                    $form_id = $arfform->create($general_option);

                    //code end here
                    //code start here for get css option and generate new css.
                    $cssoptions = $general_option['form_css'];

                    $cssoptions_db = $general_option['form_css_db'];


                    //code start here for get fields of form and store in database.
                    $type_array = array();
                    $content_array = array();
                    $value_array = array();
                    $new_id_array = array();
                    $i=0;
                    foreach ($val_main->fields->children() as $key_fields => $val_fields) {
                        
                        if( !in_array($val_fields->type,$outside_fields)){
                            /* Skip add on fields while imported form has those fields but add on is not activated */
                            continue;
                        }

                        $fields_option = array();


                        foreach ($val_fields as $key_field => $val_field) {

                            if ($key_field == 'form_id') {
                                $fields_option[$key_field] = $form_id;
                            } elseif ($key_field == 'field_key') {
                                //$fields_option[$key_field] = $armainhelper->get_unique_key('', $MdlDb->fields, 'field_key');
                            } else if ($key_field == 'options' && $val_fields->type == 'radio') {

                                if( !is_array($val_field) ){
                                    $val_field_radio = json_decode(trim($val_field),true);
                                    if( json_last_error() != JSON_ERROR_NONE ){
                                        $val_field_radio = maybe_unserialize(trim($val_field));
                                    }
                                }
                                
                                if (is_array($val_field_radio)) {
                                    foreach ($val_field_radio as $key => $value) {
                                        $image_path = '';
                                        if (is_array($value)) {                                                        
                                            if (isset($value['label_image']) && $value['label_image'] !='') {
                                                $image_path = $value['label_image'];

                                                @copy($image_path, $imageupload_dir . $key . '_' . basename($image_path));

                                                $val_field_radio[$key]['label_image'] = $imageupload_url . $key . '_' . basename($image_path);
                                            }
                                        }
                                    }
                                }
                                
                                $fields_option[$key_field] = json_encode($val_field_radio);
                            } elseif ($val_fields->type == 'imagecontrol' && $key_field == 'field_options') {

                                $arf_image_control_option = maybe_unserialize(trim($val_field));
                                $arf_image_control_image = isset($arf_image_control_option['image_url']) ? $arf_image_control_option['image_url'] : '';
                                if ($arf_image_control_image != '') {
                                    $arf_image_control_image_filenm = basename($arf_image_control_image);

                                    $arf_image_control_image_filenm_fnm = time() . '_' . $arf_image_control_image_filenm;


                                    if (!@copy($arf_image_control_image, $imageupload_dir . $arf_image_control_image_filenm_fnm)) {
                                        $arf_image_control_image_filenm_fnm = '';
                                    }
                                }

                                $arf_image_control_image_filenm_fnm = isset($arf_image_control_image_filenm_fnm) ? $arf_image_control_image_filenm_fnm : '';
                                if ($arf_image_control_image_filenm_fnm == '') {
                                    $arf_image_control_option['image_url'] = '';
                                } else {
                                    $arf_image_control_option['image_url'] = $imageupload_url . $arf_image_control_image_filenm_fnm;
                                }
                                $fields_option[$key_field] = trim(json_encode($arf_image_control_option));

                            } elseif ($val_fields->type == 'arf_switch' && $key_field == 'field_options') {

                                $arf_switch_control_option = maybe_unserialize(trim($val_field));
                                
                                $fields_option[$key_field] = trim(json_encode($arf_switch_control_option));

                            } elseif ($val_fields->type == 'arf_smiley' && $key_field == 'field_options') {
                                $arf_smiley_control_option = maybe_unserialize(trim($val_field));
                                
                                if (isset($arf_smiley_control_option['arf_smiley_images_array']) && is_array($arf_smiley_control_option['arf_smiley_images_array'])) {
                                    foreach ($arf_smiley_control_option['arf_smiley_images_array'] as $key => $value) {

                                        if (stripos($value, 'http') == 0 && !preg_match('/\s/', $value)) {
                                            $arf_smile_control_image_filenm = basename($value);

                                            $arf_smile_control_image_filenm_fnm = time() . '_' . $arf_smile_control_image_filenm;
                                            if (!@copy($exported_site_uploads_dir.$value, $imageupload_dir . $arf_smile_control_image_filenm_fnm)) {
                                                $arf_smile_control_image_filenm_fnm = '';
                                            }
                                            $arf_smiley_control_option['arf_smiley_images_array'][$key] = $arf_smile_control_image_filenm_fnm;
                                        } else {

                                            $arf_smile_control_image_filenm = basename($value);

                                            $arf_smile_control_image_filenm_fnm = time() . '_' . $arf_smile_control_image_filenm;

                                            if (!@copy($value, $imageupload_dir . $arf_smile_control_image_filenm_fnm)) {
                                                $arf_smile_control_image_filenm_fnm = '';
                                            }
                                            $arf_smiley_control_option['arf_smiley_images_array'][$key] = $value;
                                        }
                                    }
                                }



                                $fields_option[$key_field] = trim(json_encode($arf_smiley_control_option));
                                

                            } 
                             else {
                                if( $key_field == 'field_options' ){
                                    $fields_option[$key_field] = trim(json_encode(maybe_unserialize(trim($val_field))));
                                    $fields_option[$key_field] = str_replace('[ENTERKEY]', '<br>', $fields_option[$key_field]);

                                } else {
                                    $fields_option[$key_field] = trim($val_field);
                                }                                            
                            }
                            $all_field_data='';
                            $field_name='';
                            if(isset($fields_option['field_options'])){
                                $all_field_data=json_decode($fields_option['field_options']);
                                if(isset($all_field_data->name)){
                                    $field_name=str_replace('[ENTERKEY]', ' ', $all_field_data->name);
                                    $all_field_data->name=$field_name;
                                }
                                $fields_option['field_options'] = trim(json_encode($all_field_data));
                                
                            }
                            if( $key_field == 'field_options' ){
                                $arf_field_options = maybe_unserialize(trim($val_field));
                                if( isset($arf_field_options['arf_prefix_icon']) && $arf_field_options['arf_prefix_icon'] != '' ){
                                    $arf_field_options['arf_prefix_icon'] = $armainhelper->arf_update_fa_font_class( $arf_field_options['arf_prefix_icon'] );
                                }

                                if( isset($arf_field_options['arf_suffix_icon']) && $arf_field_options['arf_suffix_icon'] != '' ){
                                    $arf_field_options['arf_suffix_icon'] = $armainhelper->arf_update_fa_font_class( $arf_field_options['arf_suffix_icon'] );
                                }
                                $fields_option[$key_field] = trim(json_encode($arf_field_options));
                            }

                        }
                        $res_field_id = $fields_option['id'];
                        $type_array[$res_field_id] = $fields_option['type'];
                        //$old_field_orders                                    
                        $new_field_id = $arffield->create($fields_option, true, true, $res_field_id);
                        if($val_fields->type !='html'){
                            $new_id_array[$i]['old_id'] = $res_field_id;
                            $new_id_array[$i]['new_id'] = $new_field_id;
                            $new_id_array[$i]['name'] = $fields_option['name'];
                            $new_id_array[$i]['type'] = $fields_option['type'];
                        }
                        if ($fields_option['type'] == 'html') {
                            $value_array = json_decode($fields_option['field_options'], true);
                            if ($value_array['enable_total'] == 1) {
                                $content_array[$new_field_id]['html_content'] = addslashes($value_array['description']);
                            }
                        }
                        if ($fields_option['type'] != 'hidden') {
                            $new_field_order[$new_field_id] = $old_field_orders[$res_field_id];
                        }
                        
                        $ar_email_subject = isset($ar_email_subject) ? $ar_email_subject : '';
                        if ($ar_email_subject == '')
                            $ar_email_subject = @$_SESSION['ar_email_subject_org'];
                        else
                            $ar_email_subject = $ar_email_subject;

                        $ar_email_subject = str_replace('[' . $res_field_id . ']', '[' . $new_field_id . ']', $ar_email_subject);
                        $ar_email_subject = $arformhelper->replace_field_shortcode_import($ar_email_subject, $res_field_id, $new_field_id);

                        $ar_email_message = isset($ar_email_message) ? $ar_email_message : '';
                        if ($ar_email_message == '')
                            $ar_email_message = @$_SESSION['ar_email_message_org'];
                        else
                            $ar_email_message = $ar_email_message;

                        $ar_email_message = str_replace('[' . $res_field_id . ']', '[' . $new_field_id . ']', $ar_email_message);
                        $ar_email_message = $arformhelper->replace_field_shortcode_import($ar_email_message, $res_field_id, $new_field_id);

                        $arf_pre_dup_field = isset($arf_pre_dup_field) ? $arf_pre_dup_field : '';
                        if ($arf_pre_dup_field == '')
                            $arf_pre_dup_field = @$_SESSION['arf_pre_dup_field'];
                        else
                            $arf_pre_dup_field = $arf_pre_dup_field;

                        $arf_pre_dup_field = str_replace($res_field_id, $new_field_id, $arf_pre_dup_field);


                        $ar_admin_email_message = isset($ar_admin_email_message) ? $ar_admin_email_message : '';
                        if ($ar_admin_email_message == '')
                            $ar_admin_email_message = @$_SESSION['ar_admin_email_message_org'];
                        else
                            $ar_admin_email_message = $ar_admin_email_message;
                        $ar_admin_email_message = str_replace('[' . $res_field_id . ']', '[' . $new_field_id . ']', $ar_admin_email_message);
                        $ar_admin_email_message = $arformhelper->replace_field_shortcode_import($ar_admin_email_message, $res_field_id, $new_field_id);


                        $ar_admin_from_name = isset($ar_admin_from_name) ? $ar_admin_from_name : '';
                        if ($ar_admin_from_name == '')
                            $ar_admin_from_name = @$_SESSION['arf_admin_from_name'];
                        else
                            $ar_admin_from_name = $ar_admin_from_name;
                        $ar_admin_from_name = str_replace('[' . $res_field_id . ']', '[' . $new_field_id . ']', $ar_admin_from_name);
                        $ar_admin_from_name = $arformhelper->replace_field_shortcode_import($ar_admin_from_name, $res_field_id, $new_field_id);

                        $admin_email_subject = isset($admin_email_subject) ? $admin_email_subject : '';
                        if ($admin_email_subject == '')
                            $admin_email_subject = @$_SESSION['admin_email_subject'];
                        else
                            $admin_email_subject = $admin_email_subject;
                        $admin_email_subject = str_replace('[' . $res_field_id . ']', '[' . $new_field_id . ']', $admin_email_subject);
                        $admin_email_subject = $arformhelper->replace_field_shortcode_import($admin_email_subject, $res_field_id, $new_field_id);


                        $reply_to = isset($reply_to) ? $reply_to : '';
                        if ($reply_to == '')
                            $reply_to = @$_SESSION['reply_to'];
                        else
                            $reply_to = $reply_to;
                        $reply_to = str_replace('[' . $res_field_id . ']', '[' . $new_field_id . ']', $reply_to);
                        $reply_to = $arformhelper->replace_field_shortcode_import($reply_to, $res_field_id, $new_field_id);

                        $ar_email_to = isset($ar_email_to) ? $ar_email_to : '';
                        if ($ar_email_to == '')
                            $ar_email_to = @$_SESSION['ar_email_to_org'];
                        else
                            $ar_email_to = $ar_email_to;

                        $ar_admin_from_email = isset($ar_admin_from_email) ? $ar_admin_from_email : '';
                        if ($ar_admin_from_email == '')
                            $ar_admin_from_email = @$_SESSION['ar_admin_from_email'];
                        else
                            $ar_admin_from_email = $ar_admin_from_email;

                        $ar_admin_from_email = str_replace('[' . $res_field_id . ']', '[' . $new_field_id . ']', $ar_admin_from_email);
                        $ar_admin_from_email = $arformhelper->replace_field_shortcode_import($ar_admin_from_email, $res_field_id, $new_field_id);

                        $ar_user_from_email = isset($ar_user_from_email) ? $ar_user_from_email : '';
                        if ($ar_user_from_email == '')
                            $ar_user_from_email = @$_SESSION['ar_user_from_email'];
                        else
                            $ar_user_from_email = $ar_user_from_email;

                        $ar_user_from_email = str_replace('[' . $res_field_id . ']', '[' . $new_field_id . ']', $ar_user_from_email);
                        $ar_user_from_email = $arformhelper->replace_field_shortcode_import($ar_user_from_email, $res_field_id, $new_field_id);

                        unset($field_values);
                        $i++;
                    }
                    $running_total_fields = array();
                    if (in_array('html', $type_array)) {
                        if (!empty($content_array) && !empty($new_id_array)) {
                            foreach ($content_array as $key_type => $value_type) {
                                $arf_html_content_new = $value_type['html_content'];
                                foreach ($new_id_array as $key_new => $value_new) {
                                    $arf_html_content = ':' . $value_new["old_id"] . ']';
                                    $replace_with_arf_html_content = ':' . $value_new["new_id"] . ']';
                                    if( $value_new['type'] == 'checkbox'){
                                        
                                        $pattern_ch = "/\:(\d+)(\.\d+)/";
                                        $pattern = "/\:(\d+)/";
                                        preg_match_all($pattern,$replace_with_arf_html_content,$Matches);
                                        preg_match_all($pattern,$arf_html_content,$Matches1);
                                        if( isset($Matches[1]) && count($Matches[1]) > 0){
                                            foreach($Matches[1] as $kk => $Match){
                                                $arf_html_content_new = preg_replace($pattern_ch,':'.$Match.'$2',$arf_html_content_new);
                                                $running_total_fields[$Match][] = $key_type;
                                            }
                                        }
                                    } else {
                                        $arf_html_content_new = str_replace($arf_html_content, $replace_with_arf_html_content, $arf_html_content_new);
                                        $pattern = "/\:\d+/";
                                        preg_match_all($pattern,$arf_html_content_new,$matches);
                                        
                                        if( isset($matches[0]) && $matches[0] != '' ){
                                            foreach( $matches[0] as $k => $val ){
                                                $running_total_fields[preg_replace('/[^0-9]/','',$val)][] = $key_type;
                                            }
                                        }
                                    }
                                }
                                $fleld_data = $wpdb->get_results($wpdb->prepare("SELECT field_options FROM " . $MdlDb->fields . " WHERE id=%d" , $key_type));
                                $fleld_data_options = json_decode($fleld_data[0]->field_options, 1);
                                $fleld_data_options['description'] = addslashes($arf_html_content_new);
                                $wpdb->query("UPDATE " . $MdlDb->fields . " SET field_options ='" . json_encode($fleld_data_options) . "' WHERE id=" . $key_type);
                            }
                        }
                    }
                    $result_diff = array_diff($old_field_orders, $new_field_order);
                    foreach ($result_diff as $key => $value) {
                        $new_field_order[$key] = $value;
                    }      
                    $final_field_order = array();   
                    $new_temp_field = array();    
                    foreach ($new_field_order as $key => $value) {
                        if(strpos($key, '_confirm') !== false) {
                            
                            $field_ext_extract = explode('_', $key);
                            $old_value =  $old_field_orders[$field_ext_extract[0]];
                            $new_id = array_search($old_value, $new_field_order);
                            $final_field_order[$new_id.'_confirm'] = $value;
                            $fleld_data_confirm = $wpdb->get_results($wpdb->prepare("SELECT field_options FROM " . $MdlDb->fields . " WHERE id=%d" , $new_id));
                            $fleld_data_confirm_options = json_decode($fleld_data_confirm[0]->field_options, 1);
                            if($fleld_data_confirm_options['type'] == 'email'){
                                $new_temp_field['confirm_email_'.$new_id] = array();
                                $new_temp_field['confirm_email_'.$new_id]['key'] = $fleld_data_confirm_options['key'];
                                $new_temp_field['confirm_email_'.$new_id]['order'] = $value;
                                $new_temp_field['confirm_email_'.$new_id]['parent_field_id'] = $new_id;
                                $new_temp_field['confirm_email_'.$new_id]['confirm_inner_class'] = $fleld_data_confirm_options['confirm_email_inner_classes'];;
                                
                            }
                            if($fleld_data_confirm_options['type'] == 'password'){
                                $new_temp_field['confirm_password_'.$new_id] = array();
                                $new_temp_field['confirm_password_'.$new_id]['key'] = $fleld_data_confirm_options['key'];
                                $new_temp_field['confirm_password_'.$new_id]['order'] = $value;
                                $new_temp_field['confirm_password_'.$new_id]['parent_field_id'] = $new_id;
                                $new_temp_field['confirm_password_'.$new_id]['confirm_inner_class'] = $fleld_data_confirm_options['confirm_password_inner_classes'];
                                
                            }
                        }
                        else{
                            $final_field_order[$key] = $value;
                        }                     
                    }        
                    $running_total_fields = array_map('array_unique', array_map('array_values',$running_total_fields));

                    if( isset($running_total_fields) && count($running_total_fields) > 0 ){
                        foreach($running_total_fields as $k => $rtfield_id){
                            
                            foreach($rtfield_id as $i => $rtfield ){
                                $is_rt_enable = $wpdb->get_results($wpdb->prepare("SELECT enable_running_total FROM `".$MdlDb->fields."` WHERE id = %d",$k));
                                if( isset($is_rt_enable) && count($is_rt_enable) > 0 ){
                                    foreach($is_rt_enable as $i => $rtenable){
                                        if( isset($rtenable->enable_running_total) && $rtenable->enable_running_total != '' && $rtenable->enable_running_total > 0 ){
                                            if( strpos($rtfield,$rtenable->enable_running_total) == false )
                                                $new_total_field = $rtfield.','.$rtenable->enable_running_total;
                                            $wpdb->update($MdlDb->fields,array('enable_running_total' => $new_total_field),array('id'=> $k));
                                        } else {
                                            $wpdb->update($MdlDb->fields,array('enable_running_total' => $rtfield),array('id'=> $k));
                                        }
                                    }
                                }
                            }
                        }
                    }                                

                    $getForm = $wpdb->get_results($wpdb->prepare("SELECT options FROM `".$MdlDb->forms."` WHERE id = %d",$form_id));
                    $formOpt = maybe_unserialize($getForm[0]->options);

                    $newOpt = maybe_unserialize($general_option['options']);

                    $newOpt['arf_field_order'] = json_encode($final_field_order);

                    $general_option['options'] = maybe_serialize($newOpt);

                    $new_values = array();



                    foreach (maybe_unserialize($cssoptions) as $k => $v) {
                        if (( preg_match('/color/', $k) or in_array($k, array('arferrorbgsetting', 'arferrorbordersetting', 'arferrortextsetting')) ) && !in_array($k, array('arfcheckradiocolor'))) {
                            $new_values[$k] = str_replace('#', '', $v);
                        } else {
                            $new_values[$k] = $v;
                        }
                    }
                    $new_values1 = serialize($new_values);


                    if (!empty($new_values)) {
                        $query_results = $wpdb->query($wpdb->prepare("update " . $MdlDb->forms . " set form_css = '%s' where id = '%d'", $cssoptions_db, $form_id));

                        $use_saved = $saving = true;
                        $arfssl = (is_ssl()) ? 1 : 0;
                        $filename = FORMPATH . '/core/css_create_main.php';
                        
                        $wp_upload_dir = wp_upload_dir();
                        $target_path = $wp_upload_dir['basedir'] . '/arforms/maincss';                                    

                        $css = $warn = "/* WARNING: Any changes made to this file will be lost when your ARForms settings are updated */";

                        $css .= "\n";
                        if (ob_get_length())
                            ob_end_flush();

                        ob_start();

                        include $filename;

                        $css .= ob_get_contents();

                        ob_end_clean();



                        $css .= "\n " . $warn;
                        $css_file = $target_path . '/maincss_' . $form_id . '.css';

                        $css = str_replace('##','#',$css);
                        if (!file_exists($css_file)) {

                            WP_Filesystem();
                            global $wp_filesystem;
                            $wp_filesystem->put_contents($css_file, $css, 0777);
                        } else if (is_writable($css_file)) {

                            WP_Filesystem();
                            global $wp_filesystem;
                            $wp_filesystem->put_contents($css_file, $css, 0777);
                        } else
                            $error = 'File Not writable';

                        $filename1 = FORMPATH . '/core/css_create_materialize.php';
                        
                        $wp_upload_dir = wp_upload_dir();
                        $target_path1 = $wp_upload_dir['basedir'] . '/arforms/maincss';                                    

                        $css1 = $warn1 = "/* WARNING: Any changes made to this file will be lost when your ARForms settings are updated */";

                        $css1 .= "\n";
                        if (ob_get_length())
                            ob_end_flush();

                        ob_start();

                        include $filename1;

                        $css1 .= ob_get_contents();

                        ob_end_clean();



                        $css1 .= "\n " . $warn1;
                        $css_file1 = $target_path1 . '/maincss_materialize_' . $form_id . '.css';

                        $css1 = str_replace('##','#',$css1);                                    
                        if (!file_exists($css_file1)) {

                            WP_Filesystem();
                            global $wp_filesystem;
                            $wp_filesystem->put_contents($css_file1, $css1, 0777);
                        } else if (is_writable($css_file1)) {

                            WP_Filesystem();
                            global $wp_filesystem;
                            $wp_filesystem->put_contents($css_file1, $css1, 0777);
                        } else
                            $error = 'File Not writable';
                    }
                    else {

                        $query_results = true;
                    }
                    //code end here.
                    //code start here for update autoresponder maping variables and update in satabase..

                    ob_start();

                    $autoresponder_fname_ses = isset($_SESSION['arf_fields'][$autoresponder_fname]) ? $_SESSION['arf_fields'][$autoresponder_fname] : '';
                    $autoresponder_lname_ses = isset($_SESSION['arf_fields'][$autoresponder_lname]) ? $_SESSION['arf_fields'][$autoresponder_lname] : '';
                    $autoresponder_email_ses = isset($_SESSION['arf_fields'][$autoresponder_email]) ? $_SESSION['arf_fields'][$autoresponder_email] : '';

                    $autoresponder_fname = (isset($autoresponder_fname) and $autoresponder_fname_ses != '' ) ? $autoresponder_fname_ses : '';

                    $autoresponder_lname = (isset($autoresponder_lname) and $autoresponder_lname_ses != '') ? $autoresponder_lname_ses : '';

                    $autoresponder_email = (isset($autoresponder_email) and $autoresponder_email_ses != '') ? $autoresponder_email_ses : '';

                    $wpdb->update($MdlDb->forms, array('autoresponder_fname' => arf_sanitize_value($autoresponder_fname), 'autoresponder_lname' => arf_sanitize_value($autoresponder_lname), 'autoresponder_email' => arf_sanitize_value($autoresponder_email, 'email')), array('id' => $form_id));

                    
                    $wpdb->update($MdlDb->forms, array('options' => $general_option['options'],'temp_fields'=>maybe_serialize($new_temp_field)), array('id' => $form_id));

                    $sel_rec = $wpdb->prepare("select options from " . $MdlDb->forms . " where id = %d", $form_id);

                    $res_rec = $wpdb->get_results($sel_rec, 'ARRAY_A');

                    $opt = $res_rec[0]['options'];
                    $arf_form_other_css = @stripslashes(str_replace($old_id, $form_id, $option_arr_new['arf_form_other_css']));
                    $form_custom_css = @stripslashes(str_replace($old_id, $form_id, $val_main->form_custom_css));

                    $form_custom_css = @str_replace('[REPLACE_SITE_URL]', site_url(), $form_custom_css);

                    $form_custom_css = @str_replace('[ENTERKEY]', '<br>', $form_custom_css);

                    $option_arr_new = unserialize($opt);

                    $option_arr_new['form_custom_css'] = $form_custom_css;

                    $option_arr_new['arf_form_other_css'] = $arf_form_other_css;

                    $option_arr_new['ar_email_subject'] = isset($ar_email_subject) ? $ar_email_subject : '';

                    $option_arr_new['ar_email_message'] = isset($ar_email_message) ? $ar_email_message : '';

                    $option_arr_new['ar_admin_email_message'] = isset($ar_admin_email_message) ? $ar_admin_email_message : '';

                    $option_arr_new['ar_email_to'] = isset($ar_email_to) ? $ar_email_to : '';

                    $option_arr_new['ar_admin_from_email'] = isset($ar_admin_from_email) ? $ar_admin_from_email : '';

                    $option_arr_new['ar_user_from_email'] = isset($ar_user_from_email) ? $ar_user_from_email : '';

                    $option_arr_new['ar_admin_from_name'] = isset($ar_admin_from_name) ? $ar_admin_from_name : '';

                    $option_arr_new['admin_email_subject'] = isset($admin_email_subject) ? $admin_email_subject : '';

                    $option_arr_new['arf_pre_dup_field'] = isset($arf_pre_dup_field) ? $arf_pre_dup_field : '';

                    $option_arr_new['reply_to'] = $reply_to;

                    if ($val_main->site_url != site_url()) {
                        $option_arr_new['success_action'] = isset($option_arr_new['success_action']) ? $option_arr_new['success_action'] : '';
                        if ($option_arr_new['success_action'] == 'page')
                            $option_arr_new['success_action'] = 'message';
                    }

                    $submit_coditional_logic_rules = array();
                    $conditional_logic_new_fields = array();
                    $conditional_logic_res_fields = array();
                    if (count($_SESSION['arf_fields']) > 0 and is_array($_SESSION['arf_fields'])) {
                        if (!empty($option_arr_new['submit_conditional_logic']) && $option_arr_new['submit_conditional_logic']['enable'] == '1' && count($option_arr_new['submit_conditional_logic']['rules']) > 0) {
                            foreach ($_SESSION['arf_fields'] as $original_id => $field_new_id) {
                                foreach ($option_arr_new['submit_conditional_logic']['rules'] as $new_rule) {
                                    if ($new_rule['field_id'] == $original_id) {
                                        
                                        $sub_cl_field_id = $field_new_id;
                                        $sub_cl_field_type = $new_rule['field_type'];
                                        
                                        $field_type_db = $wpdb->get_row($wpdb->prepare("SELECT type FROM `".$MdlDb->fields."` WHERE id = %d",$sub_cl_field_id) );

                                        if( isset($field_type_db) && isset($field_type_db->type) && $sub_cl_field_type != $field_type_db ){
                                            $new_rule['field_type'] = $field_type_db->type;
                                        }

                                        $submit_coditional_logic_rules[$new_rule['id']] = array(
                                            'id' => $new_rule['id'],
                                            'field_id' => $field_new_id,
                                            'field_type' => $new_rule['field_type'],
                                            'operator' => $new_rule['operator'],
                                            'value' => $new_rule['value'],
                                        );
                                        array_push($conditional_logic_new_fields,$new_rule['field_id']);
                                    }
                                }
                            }
                        }
                    }
                    if (isset($submit_coditional_logic_rules) && !empty($submit_coditional_logic_rules)) {
                        $option_arr_new['submit_conditional_logic']['rules'] = $submit_coditional_logic_rules;
                    }

                    /* added for conditional mail rules */
                    $arf_conditional_mail_rules = array();
                    if (count($_SESSION['arf_conditional_mail_rules']) > 0 and is_array($_SESSION['arf_conditional_mail_rules'])) {
                        if (!empty($option_arr_new['arf_conditional_mail_rules'])) {
                            foreach ($option_arr_new['arf_conditional_mail_rules'] as $new_rule) {
                                $_SESSION['arf_fields'][$new_rule['field_id_mail']] = isset($_SESSION['arf_fields'][$new_rule['field_id_mail']]) ? $_SESSION['arf_fields'][$new_rule['field_id_mail']] : '';
                                $_SESSION['arf_fields'][$new_rule['send_mail_field']] = isset($_SESSION['arf_fields'][$new_rule['send_mail_field']]) ? $_SESSION['arf_fields'][$new_rule['send_mail_field']] : '';

                                $cl_email_field_id = $_SESSION['arf_fields'][$new_rule['field_id_mail']];
                                $cl_email_field_type = $new_rule['field_type_mail'];

                                $email_field_type = $wpdb->get_row($wpdb->prepare("SELECT type FROM `".$MdlDb->fields."` WHERE id = %d",$cl_email_field_id));

                                if( isset($email_field_type) && isset($email_field_type->type) && $email_field_type->type != $cl_email_field_type ){
                                    $new_rule['field_type_mail'] = $email_field_type->type;
                                }

                                $arf_conditional_mail_rules[$new_rule['id_mail']] = array(
                                    'id_mail' => $new_rule['id_mail'],
                                    'field_id_mail' => $_SESSION['arf_fields'][$new_rule['field_id_mail']], 
                                    'field_type_mail' => $new_rule['field_type_mail'],
                                    'operator_mail' => $new_rule['operator_mail'],
                                    'value_mail' => $new_rule['value_mail'],
                                    'send_mail_field' => $_SESSION['arf_fields'][$new_rule['send_mail_field']]
                                );
                            }
                        }
                    }
                    if (isset($arf_conditional_mail_rules) && !empty($arf_conditional_mail_rules)) {
                        $option_arr_new['arf_conditional_mail_rules'] = $arf_conditional_mail_rules;
                    }
                    /* for conditional logic new */

                    $conditional_logic = isset($option_arr_new['arf_conditional_logic_rules']) ? $option_arr_new['arf_conditional_logic_rules'] : array();
                    
                    if (is_array($conditional_logic) && !empty($conditional_logic)) {
                        foreach ($conditional_logic as $i => $value_rules) {
                            if (isset($value_rules['condition']) && is_array($value_rules['condition'])) {
                                foreach ($value_rules['condition'] as $j => $condition_rules) {
                                    
                                    $arf_cl_rule_field_id = $_SESSION['arf_fields'][$condition_rules['field_id']];
                                    $arf_cl_rule_field_type = $condition_rules['field_type'];

                                    $cl_rule_type = $wpdb->get_row($wpdb->prepare("SELECT type FROM `".$MdlDb->fields."` WHERE id = %d",$arf_cl_rule_field_id));

                                    if( isset($cl_rule_type) && isset($cl_rule_type->type) && $cl_rule_type->type != $arf_cl_rule_field_type ){
                                        $conditional_logic[$i]['condition'][$j]['field_type'] = $cl_rule_type->type;
                                    }

                                    $conditional_logic[$i]['condition'][$j]['field_id'] = $_SESSION['arf_fields'][$condition_rules['field_id']];
                                    array_push($conditional_logic_new_fields,$_SESSION['arf_fields'][$condition_rules['field_id']]);
                                }
                            }

                            if (isset($value_rules['result']) && is_array($value_rules['result'])) {

                                foreach ($value_rules['result'] as $k => $result_rules) {

                                    $arf_cl_res_field_id = $_SESSION['arf_fields'][$result_rules['field_id']];
                                    $arf_cl_res_field_type = $result_rules['field_type'];

                                    $rs_rule_type = $wpdb->get_row($wpdb->prepare("SELECT type FROM `".$MdlDb->fields."` WHERE id = %d",$arf_cl_res_field_id));

                                    if( isset($rs_rule_type) && isset($rs_rule_type->type) && $rs_rule_type->type != $arf_cl_res_field_type ){
                                        $conditional_logic[$i]['result'][$k]['field_type'] = $rs_rule_type->type;
                                    }

                                    $conditional_logic[$i]['result'][$k]['field_id'] = isset($_SESSION['arf_fields'][$result_rules['field_id']]) ? $_SESSION['arf_fields'][$result_rules['field_id']] : "";
                                    array_push($conditional_logic_res_fields,$conditional_logic[$i]['result'][$k]['field_id']);
                                }
                            }
                        }
                        $option_arr_new['arf_conditional_logic_rules'] = $conditional_logic;
                    }

                    $option_arr_new = serialize(apply_filters('arf_import_update_field_outside', $option_arr_new, $_SESSION['arf_fields'], $form_id));

                    $wpdb->update($MdlDb->forms, array('options' => $option_arr_new), array('id' => $form_id));
                    if( isset($conditional_logic_new_fields) && count($conditional_logic_new_fields) > 0 ){
                        $conditional_logic_new_fields = array_unique($conditional_logic_new_fields);
                        foreach( $conditional_logic_new_fields as $ncfk => $new_cl_field_id){
                            $wpdb->update($MdlDb->fields,array('conditional_logic'=>1),array('id'=>$new_cl_field_id));
                        }
                    }                               
                    if ($val_main->site_url == site_url()) {
                        $aweber = array();
                        foreach ($val_main->autoresponder->aweber->children() as $autores_key1 => $autores_val1) {
                            $aweber[$autores_key1] = @(string) trim($autores_val1);
                        }
                        $aweber = maybe_serialize($aweber);

                        $mailchimp = array();
                        foreach ($val_main->autoresponder->mailchimp->children() as $autores_key1 => $autores_val1) {
                            $mailchimp[$autores_key1] = @(string) trim($autores_val1);
                        }
                        $mailchimp = maybe_serialize($mailchimp);                                    

                        $madmimi = array();
                        foreach ($val_main->autoresponder->madmimi->children() as $autores_key1 => $autores_val1) {
                            $madmimi[$autores_key1] = @(string) trim($autores_val1);
                        }
                        $madmimi = maybe_serialize($madmimi);

                        $getresponse = array();
                        foreach ($val_main->autoresponder->getresponse->children() as $autores_key1 => $autores_val1) {
                            $getresponse[$autores_key1] = @(string) trim($autores_val1);
                        }
                        $getresponse = maybe_serialize($getresponse);

                        $gvo = array();
                        foreach ($val_main->autoresponder->gvo->children() as $autores_key1 => $autores_val1) {
                            $gvo[$autores_key1] = @(string) trim($autores_val1);
                        }
                        $gvo = maybe_serialize($gvo);

                        $ebizac = array();
                        foreach ($val_main->autoresponder->ebizac->children() as $autores_key1 => $autores_val1) {
                            $ebizac[$autores_key1] = @(string) trim($autores_val1);
                        }
                        $ebizac = maybe_serialize($ebizac);

                        $icontact = array();
                        foreach ($val_main->autoresponder->icontact->children() as $autores_key1 => $autores_val1) {
                            $icontact[$autores_key1] = @(string) trim($autores_val1);
                        }
                        $icontact = maybe_serialize($icontact);

                        $constant_contact = array();
                        foreach ($val_main->autoresponder->constant_contact->children() as $autores_key1 => $autores_val1) {
                            $constant_contact[$autores_key1] = @(string) trim($autores_val1);
                        }
                        $constant_contact = maybe_serialize($constant_contact);

                        $mailerlite = array();
                        foreach ($val_main->autoresponder->mailerlite->children() as $autores_key1 => $autores_val1) {
                            $mailerlite[$autores_key1] = @(string) trim($autores_val1);
                        }
                        $mailerlite = maybe_serialize($mailerlite);

                    } else {
                        global $wpdb, $MdlDb;
                        $res = @unserialize(get_option('arf_ar_type'));

                        $res1 = $wpdb->get_results($wpdb->prepare("SELECT * FROM " . $MdlDb->autoresponder . " WHERE responder_id = %d", 3), 'ARRAY_A');
                        $res2 = $wpdb->get_results($wpdb->prepare("SELECT * FROM " . $MdlDb->autoresponder . " WHERE responder_id = %d", 1), 'ARRAY_A');
                        $res3 = $wpdb->get_results($wpdb->prepare("SELECT * FROM " . $MdlDb->autoresponder . " WHERE responder_id = %d", 4), 'ARRAY_A');
                        $res4 = $wpdb->get_results($wpdb->prepare("SELECT * FROM " . $MdlDb->autoresponder . " WHERE responder_id = %d", 5), 'ARRAY_A');
                        $res5 = $wpdb->get_results($wpdb->prepare("SELECT * FROM " . $MdlDb->autoresponder . " WHERE responder_id = %d", 6), 'ARRAY_A');
                        $res6 = $wpdb->get_results($wpdb->prepare("SELECT * FROM " . $MdlDb->autoresponder . " WHERE responder_id = %d", 8), 'ARRAY_A');
                        $res7 = $wpdb->get_results($wpdb->prepare("SELECT * FROM " . $MdlDb->autoresponder . " WHERE responder_id = %d", 9), 'ARRAY_A');
                        $res11 = $wpdb->get_results($wpdb->prepare("SELECT * FROM " . $MdlDb->autoresponder . " WHERE responder_id = %d", 10), 'ARRAY_A');

                        $aweber_arr['enable'] = isset($res['aweber_type']) ? $res['aweber_type'] : '';
                        $aweber_arr['is_global'] = 1;
                        $aweber_arr['type'] = isset($res['aweber_type']) ? $res['aweber_type'] : '';
                        $aweber_arr['type_val'] = isset($res1[0]['responder_web_form']) ? $res1[0]['responder_web_form'] : '';

                        $aweber = serialize($aweber_arr);

                        $mailchimp_arr['enable'] = isset($res['mailchimp_type']) ? $res['mailchimp_type'] : '';
                        $mailchimp_arr['is_global'] = 1;
                        $mailchimp_arr['type'] = isset($res['mailchimp_type']) ? $res['mailchimp_type'] : '';
                        $mailchimp_arr['type_val'] = isset($res2[0]['responder_web_form']) ? $res2[0]['responder_web_form'] : '';

                        $mailchimp = serialize($mailchimp_arr);

                        $madmimi_arr['enable'] = isset($res['madmimi_type']) ? $res['madmimi_type'] : '';
                        $madmimi_arr['is_global'] = 1;
                        $madmimi_arr['type'] = isset($res['madmimi_type']) ? $res['madmimi_type'] : '';

                        $madmimi = serialize($madmimi_arr);

                        $getresponse_arr['enable'] = isset($res['getresponse_type']) ? $res['getresponse_type'] : '';
                        $getresponse_arr['is_global'] = 1;
                        $getresponse_arr['type'] = isset($res['getresponse_type']) ? $res['getresponse_type'] : '';
                        $getresponse_arr['type_val'] = isset($res3[0]['responder_web_form']) ? $res3[0]['responder_web_form'] : '';

                        $getresponse = serialize($getresponse_arr);

                        $gvo_arr['enable'] = isset($res['gvo_type']) ? $res['gvo_type'] : '';
                        $gvo_arr['is_global'] = 1;
                        $gvo_arr['type'] = $res['gvo_type'];
                        $gvo_arr['type_val'] = isset($res4[0]['responder_web_form']) ? $res4[0]['responder_web_form'] : '';

                        $gvo = serialize($gvo_arr);

                        $ebizac_arr['enable'] = isset($res['ebizac_type']) ? $res['ebizac_type'] : '';
                        $ebizac_arr['is_global'] = 1;
                        $ebizac_arr['type'] = isset($res['ebizac_type']) ? $res['ebizac_type'] : '';
                        $ebizac_arr['type_val'] = isset($res5[0]['responder_web_form']) ? $res5[0]['responder_web_form'] : '';

                        $ebizac = serialize($ebizac_arr);

                        $icontact_arr['enable'] = isset($res['icontact_type']) ? $res['icontact_type'] : '';
                        $icontact_arr['is_global'] = 1;
                        $icontact_arr['type'] = isset($res['icontact_type']) ? arf_sanitize_value($res['icontact_type'], 'integer') : '';
                        $icontact_arr['type_val'] = isset($res6[0]['responder_web_form']) ? arf_sanitize_value($res6[0]['responder_web_form']) : '';

                        $icontact = serialize($icontact_arr);

                        $constant_contact_arr['enable'] = isset($res['constant_contact_type']) ? arf_sanitize_value($res['constant_contact_type'], 'integer')  : '';
                        $constant_contact_arr['is_global'] = arf_sanitize_value(1, 'integer');
                        $constant_contact_arr['type'] = isset($res['constant_contact_type']) ? arf_sanitize_value($res['constant_contact_type'], 'integer') : '';
                        $constant_contact_arr['type_val'] = isset($res7[0]['responder_web_form']) ? arf_sanitize_value($res7[0]['responder_web_form']) : '';

                        $constant_contact = serialize($constant_contact_arr);

                        $mailerlite_arr['enable'] = isset($res['mailerlite_type']) ? arf_sanitize_value($res['mailerlite_type'], 'integer') : '';
                        $mailerlite_arr['is_global'] = arf_sanitize_value(1, 'integer');
                        $mailerlite_arr['type'] = isset($res['mailerlite_type']) ? arf_sanitize_value($res['mailerlite_type']) : '';

                        $mailerlite = serialize($mailerlite_arr);
                    }
                    $frm_id = $form_id;

                    $update = $wpdb->query($wpdb->prepare("insert into " . $MdlDb->ar . " (aweber ,mailchimp, getresponse, gvo, ebizac,madmimi , icontact, constant_contact,mailerlite, enable_ar,  frm_id) values ('%s', '%s', '%s', '%s', '%s','%s', '%s', '%s', '%s', '%s', '%d')", $aweber, $mailchimp, $getresponse, $gvo, $ebizac, $madmimi, $icontact, $constant_contact, $mailerlite, @trim($val_main->autoresponder->enable_ar), $frm_id));

                    $id = isset($id) ? $id : '';
                    $record = isset($record) ? $record : '';
                    if ($id)
                        $resopt = @$wpdb->get_row($wpdb->prepare("select * from " . $MdlDb->forms . " where id =%d",$id), 'ARRAY_A');

                    $resopt = isset($resopt) ? $resopt : array();

                    $opt = isset($resopt["form_css"]) ? $resopt["form_css"] : '';
                    $formname = isset($resopt["name"]) ? arf_sanitize_value($resopt["name"]) : '';
                    $description = isset($resopt["description"]) ? arf_sanitize_value($resopt["description"]) : '';
                    
                    $autoresponder_fname = isset($resopt["autoresponder_fname"]) ? arf_sanitize_value($resopt["autoresponder_fname"]) : '';
                    $autoresponder_lname = isset($resopt["autoresponder_lname"]) ? arf_sanitize_value($resopt["autoresponder_lname"]) : '';
                    $autoresponder_email = isset($resopt["autoresponder_email"]) ? arf_sanitize_value($resopt["autoresponder_email"], 'email') : '';

                    $update = $wpdb->query($wpdb->prepare("update " . $MdlDb->forms . " set name = '%s' , description = '%s', autoresponder_fname = '%s', autoresponder_lname = '%s', autoresponder_email = '%s', form_css = '%s' where id = '%d'", $formname, $description, $autoresponder_fname, $autoresponder_lname, $autoresponder_email, $opt, $record));
                    //code end here.


                    if (version_compare($xml_arf_version, '2.7.4', '>=')) {
                        if (isset($val_main->form_entries) && count($val_main->form_entries->children()) > 0) {
                            include_once(FORMPATH . '/js/filedrag/simple_image.php');
                            global $user_ID, $wpdb;
                            $entry_values = array();
                            $entry_values_new = array();
                            $vls = array();
                            $entry_values['form_id'] = arf_sanitize_value($frm_id, 'integer');
                            if ($user_ID) {
                                $entry_values['user_id'] = arf_sanitize_value($user_ID, 'integer');
                            }
                            foreach ($val_main->form_entries->children() as $key_fields => $val_fields) {
                                $upload_files = array();
                                $entry_values['entry_key'] = $armainhelper->get_unique_key('', $MdlDb->entries, 'entry_key');

                                foreach ($val_fields as $key_field => $val_field) {

                                     
                                    $field_nm = str_replace('_ARF_', ' ', @(string)$val_field['field_label']);
                                    $field_nm = str_replace('_ARF_SLASH_', '/', $field_nm);

                                   
                                    
                                    if ($field_nm == 'Browser') {
                                        $entry_values['browser_info'] = arf_sanitize_value(@(string) $val_field);
                                    } else if ($field_nm == 'Country') {
                                        $entry_values['country'] = arf_sanitize_value(@(string) $val_field);
                                    } else if ($field_nm == "Created Date") {
                                        $entry_values['created_date'] = arf_sanitize_value(@(string) $val_field);
                                    } else if ($field_nm == "IP Address") {
                                        $entry_values['ip_address'] = arf_sanitize_value(@(string) $val_field);
                                    } else if ($field_nm == "Submit Type") {

                                        $vls['form_display_type'] = @(string) trim($val_field);
                                    } else {
                                        $field_data = $wpdb->get_results($wpdb->prepare("SELECT * FROM " . $MdlDb->fields . " WHERE form_id = %d", $frm_id));
                                        foreach ($field_data as $k => $v) {
                                                
                                            if ($v->name == $field_nm) {
                                                $field_type = $val_field->attributes();
                                                $entry_value = array();
                                                if ($field_type['field_type'] == 'file' && $v->type == 'file' && trim($val_field) != '' ) {

                                                    $old_values_field = explode('|', trim($val_field));

                                                    foreach ($old_values_field as $old_val_field) {
                                                         $newfilename = @(string) $old_val_field;
                                                        $image_url = $newfilename;
                                                        $img_url = explode('/', $image_url);
                                                        $img_url = $img_url[count($img_url) - 1];
                                                        $file_upload_field_key = $v->field_key;
                                                                    
                                                                    /******/
                                                        $new_file = basename($newfilename);
                                                        $full_image_name = pathinfo($newfilename);
                                                        $image_name = $full_image_name['filename'];
                                                        $image_extention = $full_image_name['extension'];
                                                        $file_path = $arformhelper->get_file_upload_path();
                                                        $file_path = $arformhelper->replace_file_upload_path_shrtcd($file_path, $form_id);
                                                        $upload_baseurl = get_home_url() . "/" . $file_path;
                                                        $upload_basepath = ABSPATH . $file_path;
                                                        $image_path = $upload_baseurl . $new_file;
                                                        $image_path1 = $upload_basepath . $new_file;
                                                        $info = @getimagesize($image_path1);
                                                        $mime_type = $info['mime'];
                                                        $args = array("post_title" => $image_name . '.' . $image_extention, 'post_name' => arf_sanitize_value($image_name), 'post_type' => arf_sanitize_value('attachment'), 'post_mime_type' => arf_sanitize_value($mime_type), "guid" => $image_path);
                                                        $entry_value[] = $lastid = wp_insert_post($args);
                                                        $path = '';
                                                        if (preg_match('/image\//', $mime_type)) {
                                                            $path = $file_path;
                                                            $uploading_image = new SimpleImage();
                                                            $uploading_image->load($upload_basepath . $new_file);
                                                            $uploading_image->resizeToHeight(100);
                                                            $uploading_image->save($upload_basepath . 'thumbs/' . $new_file);
                                                        }
                                                        else {
                                                            $path = $file_path . "thumbs/";
                                                        }
                                                        $wpdb->query($wpdb->prepare("insert into " . $wpdb->prefix . "postmeta (post_id,meta_key,meta_value) values ('%d','_wp_attached_file','%s')", arf_sanitize_value($lastid), $path . $new_file));
                                                    }
                                                    $entry_values_new ['item_meta'][$v->id] = implode('|', $entry_value);
                                                    $entry_value = array();
                                            }
                                            else {
                                                if (strtolower($field_type) == 'checkbox') {
                                                    $values = explode('^|^', @(string) $val_field);
                                                    $entry_values_new['item_meta'][$v->id] = array_map('trim',$values);
                                                } else {
                                                    $entry_values_new['item_meta'][$v->id] = @(string) trim($val_field);
                                                }
                                            }
                                        }
                                    }
                                }
                                $referrerinfo = $armainhelper->get_referer_info();
                                $entry_values['browser_info'] = isset($entry_values['browser_info']) ? arf_sanitize_value($entry_values['browser_info']) : '';
                                $entry_values['description'] = maybe_serialize(array('browser' => arf_sanitize_value($entry_values['browser_info']), 'referrer' => $referrerinfo));
                            }

                            $create_entry = true;
                            if ($create_entry) {
                                $query_results = $wpdb->insert($MdlDb->entries, $entry_values);
                            }
                            if (isset($query_results) and $query_results) {
                                $entry_id = $wpdb->insert_id;
                                global $arfsavedentries;
                                $arfsavedentries[] = (int) $entry_id;
                                if (isset($vls['form_display_type']) and $vls['form_display_type'] != '') {
                                    global $wpdb;
                                    $arf_meta_insert = array(
                                        'entry_value' => $vls['form_display_type'],
                                        'field_id' => arf_sanitize_value(0, 'integer'),
                                        'entry_id' => arf_sanitize_value($entry_id, 'integer'),
                                        'created_date' => current_time('mysql'),
                                    );
                                    $wpdb->insert($wpdb->prefix . 'arf_entry_values', $arf_meta_insert, array('%s', '%d', '%d', '%s'));
                                    
                                }

                                        if (isset($entry_values_new['item_meta']))
                                            $arfrecordmeta->update_entry_metas($entry_id, $entry_values_new['item_meta']);
                                    }
                                }

                            }
                        }

                }
                return wp_send_json(array('success'=>1,'redirect'=>admin_url().'admin.php?page=ARForms&arfaction=edit&id='.$frm_id));         
            }else{
                return wp_send_json(array('error'=>1,'message'=>addslashes(esc_html__('There is something wrong while installing Sample. Please try again.', 'ARForms'))));
            }
        return $xmldata;
    }
}
?>