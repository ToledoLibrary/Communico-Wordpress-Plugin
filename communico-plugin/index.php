<?php

/*
Plugin Name: Communico Data Puller - built with CodeWP
Description: A plugin to pull data from Communico API
Version: 1.0
Author: Toledo Lucas County Public Library
*/

class CommunicoDataPuller {
    private $client_id;
    private $client_secret;
    private $access_token;

    public function __construct() {
        // Add client ID and client secret as options
        $this->client_id = get_option('communico_client_id');
        $this->client_secret = get_option('communico_client_secret');
        $this->access_token = $this->getAccessToken();

        add_action('admin_menu', array($this, 'addPluginPage'));
        add_action('admin_init', array($this, 'registerSettings'));
        add_action('admin_head', array($this, 'addButtonToEditor'));
        add_action('wp_ajax_get_communico_data', array($this, 'getCommunicoData'));
        add_action('wp_ajax_nopriv_get_communico_data', array($this, 'getCommunicoData'));

        add_shortcode('communico', array($this, 'renderCommunicoShortcode'));
    }

    public function addButtonToEditor() {
        add_filter("mce_external_plugins", array($this, "addButtonPlugin"));
        add_filter('mce_buttons', array($this, 'registerButton'));
      
    }

    public function registerButton($buttons) {
        
        $buttons[] = 'communicoButton';
        return $buttons;
    }

    public function addButtonPlugin($plugin_array) {
        $plugin_array['communicoButton'] = plugins_url('/js/communico-button.js?v=323456718', __FILE__);
        return $plugin_array;
    }

    public function addPluginPage() {
        add_menu_page(
            'Communico Settings',
            'Communico Settings',
            'manage_options',
            'communico-settings',
            array($this, 'createAdminPage'),
            '',
            100
        );
    }

    public function createAdminPage() {
        // Set class property
        $this->options = get_option('communico_option');
        ?>
        <div class="wrap">
            <h1>Communico Settings</h1>
            <form method="post" action="options.php">
            <?php
                // This prints out all hidden setting fields
                settings_fields('communico_option_group');
                do_settings_sections('communico-setting-admin');
                submit_button();
            ?>
            </form>
        </div>
        <?php
    }

    public function registerSettings() {
        // Register client ID and client secret as settings fields
        register_setting('communico_option_group', 'communico_client_id');
        register_setting('communico_option_group', 'communico_client_secret');

        add_settings_section('communico_setting_section', 'Communico API Settings', function() {}, 'communico-setting-admin');

        add_settings_field('communico_client_id', 'Client ID', function() {
            $client_id = get_option('communico_client_id');
            echo '<input type="text" name="communico_client_id" value="' . $client_id . '" />';
        }, 'communico-setting-admin', 'communico_setting_section');

        add_settings_field('communico_client_secret', 'Client Secret', function() {
            $client_secret = get_option('communico_client_secret');
            echo '<input type="text" name="communico_client_secret" value="' . $client_secret . '" />';
        }, 'communico-setting-admin', 'communico_setting_section');
    }


    private function getAccessToken() {
        $url = "https://api.communico.co/v3/token";
        $args = array(
            'method' => 'POST',
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded'
            ),
            'body' => array(
                'client_id' => $this->client_id,
                'client_secret' => $this->client_secret,
                'grant_type' => 'client_credentials'
            ),
        );

        $response = wp_remote_request($url, $args);

        if (!is_wp_error($response)) {
            $body = json_decode(wp_remote_retrieve_body($response));
            return $body->access_token;
        }

        return null;
    }

    public function getCommunicoData() {
        $url = "https://api.communico.co/v3/attend/events";
        $args = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->access_token
            )
        );

        $response = wp_remote_get($url, $args);

        if (!is_wp_error($response)) {
            echo wp_remote_retrieve_body($response);
        }

        wp_die();
    }

    public function renderCommunicoShortcode($atts) {
        $atts = shortcode_atts(array(
            'locationid' => '',
            'ages' => '',
            'types' => '',
            'term' => ''
        ), $atts);

        if ($atts['locationid'] ) { $data .= '&locationId=' . $atts['locationid'];}
        if ($atts['ages'] ) { $data .= '&ages=' . $atts['ages'];}
        if ($atts['types'] ) { $data .= '&types=' . $atts['types'];}
        if ($atts['term'] ) { $data .= '&term=' . $atts['term'];}

        $response = $this->getCommunicoDataFromAPI($data);

        // Process the response and generate HTML elements for each event
        if ($response) {
            $responseData = json_decode($response);
            
            foreach ($responseData->data->entries as $entry) {
                    if ($entry->modified != 'canceled' and $entry->modified !='rescheduled' ) :
                        $html .= '<div class="book-group-event">';
                             if ($entry->featuredImage != null) {
                                $html .=  '<div class="book-group-event-image" data-image="featuredImage for ' . $entry->title . '" style="background-image: url(' . $entry->featuredImage . ')"></div>';
                             }
                             elseif ($entry->eventImage != null) {
                                $html .=  '<div class="book-group-event-image" data-image="eventImage for ' . $entry->title . '" style="background-image: url(' . $entry->eventImage . ')"></div>';
                             }
                             //endif;
                            $html .= '<div class="book-group-event-info">
                                <h3 class="book-group-event-title">';

                                if ($entry->featuredImage != null || $entry->eventImage != null) {
                                    $html .= $entry->title;
                                    //echo $entry->subTitle;
                                }

                                else {
                                        if ($entry->title) {
                                        $html .=  $entry->title;
                                    }
                                }
                                $html .= '
                                </h3>
                                <h4>
                                ';
                                if ($entry->featuredImage != null || $entry->eventImage != null) {

                                    $html .=  $entry->subTitle;
                                }
                                $html .= '
                                </h4>
                                <p class="event-info-date">
                                    ';
                                    if ($entry->eventStart) {
                                        $startDate = new DateTime($entry->eventStart);
                                        $endDate = new DateTime($entry->eventEnd);

                                        $stringtoclean = '' . str_replace('m', '.m.', $startDate->format('l, F j | g:i a')) . ' &mdash; ' . str_replace('m', '.m.',$endDate->format('g:i a')) .'';
                                        $stringtoclean = str_replace(".M.arch","March", $stringtoclean);
                                        $stringtoclean = str_replace(".M.ay","May", $stringtoclean);
                                        $stringtoclean = str_replace("Septe.m.ber","September", $stringtoclean);
                                        $stringtoclean = str_replace("Nove.m.ber","November", $stringtoclean);
                                        $stringtoclean = str_replace("Dece.m.ber","December", $stringtoclean);
                                        $stringtoclean = str_replace(",","", $stringtoclean);
                                        $stringtoclean = str_replace(":00","", $stringtoclean);
                                        $stringtoclean = str_replace("Monday","(M)", $stringtoclean);
                                        $stringtoclean = str_replace("Tuesday","(Tu)", $stringtoclean);
                                        $stringtoclean = str_replace("Wednesday","(W)", $stringtoclean);
                                        $stringtoclean = str_replace("Thursday","(Th)", $stringtoclean);
                                        $stringtoclean = str_replace("Friday","(F)", $stringtoclean);
                                        $stringtoclean = str_replace("Saturday","(Sa)", $stringtoclean);
                                        $stringtoclean = str_replace("Sunday","(Su)", $stringtoclean);
                                        $stringtoclean = str_replace("12 p.m.","noon", $stringtoclean);

                                        $stringtocleancountam = substr_count($stringtoclean, "a.m");
                                            $stringtocleancountpm =  substr_count($stringtoclean, "p.m");

                                            if ($stringtocleancountam == "2")
                                            {
                                                $stringtoclean = str_replace("a.m.","", $stringtoclean);
                                                $stringtoclean = $stringtoclean . ' a.m.' ;
                                            }

                                            if ($stringtocleancountpm == "2")
                                            {
                                                $stringtoclean = str_replace("p.m.","", $stringtoclean);
                                                $stringtoclean = $stringtoclean . ' p.m.' ;

                                            }

                                        $html .= $stringtoclean;
                                    }

                                    if ($entry->locationName) {
                                        $html .= ' | ' . $entry->locationName;
                                    }
                                    $html .= '
                                </p>
                                <div>
                                    <p>
                                    ';
                                        $html .=  $entry->shortDescription;
                                    $html .= '
                                    </p>
                                </div>
                                ';
                                if ($entry->eventRegistrationUrl) :
                                    $html .= '<a class="fusion-button button-flat fusion-button-default-size button-default button-7 fusion-button-default-span fusion-button-default-type" href="'. $entry->eventRegistrationUrl. '">Register</a>';
                                else :
                                    $html .= '<a class="fusion-button button-flat fusion-button-default-size button-default button-7 fusion-button-default-span fusion-button-default-type" href="http://events.toledolibrary.org/event/' . $entry->eventId . '">Read More</a>';
                                endif;
                            $html .= '</div>
                        </div>
                    ';
                    endif;

            }
            
        }

        else {
            //$html = 'No events found. URL used: ' . $this->getCommunicoDataUrl($data);
              $html = 'We currently do not have any programs scheduled at this time. Please check back soon.';
        }

        return $html;
    }

    private function getCommunicoDataFromAPI($data) {
        $startDate = date('Y-m-d');
        $url = 'https://api.communico.co/v3/attend/events?status=published&privateEvents=false&fields=eventType,types,ages,reportingCategory,eventRegistrationUrl,featuredImage,eventImage,searchTags&startDate=' . $startDate . $data;
        $args = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->access_token
            ),
            
        );

        $response = wp_remote_get($url, $args);

        if (!is_wp_error($response)) {
            return wp_remote_retrieve_body($response);
        }

        return null;
    }
}

new CommunicoDataPuller();
?>