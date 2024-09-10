<?php

/*
Plugin Name: Communico Attend Data Puller
Description: A plugin to pull data from Communico API
Version: 1.1
Author: Toledo Lucas County Public Library
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html
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
        //wp_enqueue_script('communicoButton', plugins_url('/js/communico-button.js?v=32345678', __FILE__), array('jquery'), '', true);
    }

    public function registerButton($buttons) {
        //array_push($buttons, "communicoButton");
        $buttons[] = 'communicoButton';
        return $buttons;
    }

    public function addButtonPlugin($plugin_array) {
        $plugin_array['communicoButton'] = plugins_url('/js/communico-button.js?v=12', __FILE__);
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
            $html .= '<input type="text" name="communico_client_id" value="' . $client_id . '" />';
        }, 'communico-setting-admin', 'communico_setting_section');

        add_settings_field('communico_client_secret', 'Client Secret', function() {
            $client_secret = get_option('communico_client_secret');
            $html .= '<input type="text" name="communico_client_secret" value="' . $client_secret . '" />';
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
            $html .= wp_remote_retrieve_body($response);
        }

        wp_die();
    }

    public function renderCommunicoShortcode($atts) {
        $atts = shortcode_atts(array(
            'formatstyle' => '',
            'locationid' => '',
            'ages' => '',
            'types' => '',
            'term' => '',
            'removeText' => ''
        ), $atts);

        $i = 0;

        if ($atts['formatstyle'] ) { $data .= '&formatstyle=' . $atts['formatstyle'];}
        if ($atts['locationid'] ) { $data .= '&locationId=' . $atts['locationid'];}
        if ($atts['ages'] ) { $data .= '&ages=' . $atts['ages'];}
        if ($atts['types'] ) { $data .= '&types=' . $atts['types'];}
        if ($atts['term'] ) { $data .= '&term=' . $atts['term'];}
        if ($atts['removeText'] ) { $data .= '&removeText=' . $atts['removeText'];}

        $response = $this->getCommunicoDataFromAPI($data);

        //$html .= $response;

        if (empty($response)) {
            $html = 'We currently do not have any programs scheduled at this time. Please check back soon.';
        }

        else {

            if ($atts['formatstyle'] == "storytime") {

                 $responseData = json_decode($response);

             if (empty($responseData)) {
                $html = 'We currently do not have any programs scheduled at this time. Please check back soon.';
            }

             foreach ($responseData->data->entries as $theEvent) {
        if ($theEvent->locationName == "Birmingham" && $birmcount < "3") {
            $eventStartDate = new DateTime($theEvent->eventStart);
            $eventEndate = new DateTime($theEvent->eventEnd);
            if ($birmtitle != "1") {
                $html .= '<h4 style="margin-bottom:5px"><a href="http://events.toledolibrary.org/events?v=grid&t=Storytime&l=Birmingham">' . $theEvent->locationName . '</a></h4>';
                $birmtitle = "1";
            }
            $html .= '<div class="story-time-block">';
           //  $html .= '' . str_replace('m', '.m.',$eventStartDate->format('D, F j | g:i a')) . ' &mdash; ' . str_replace('m', '.m.',$eventEndate->format('g:i a'));

            $startDate = new DateTime($theEvent->eventStart);
            $endDate = new DateTime($theEvent->eventEnd);

            $stringtoclean = '' . str_replace('m', '.m.', $startDate->format('l, F j | g:i a')) . ' &mdash; ' . str_replace('m', '.m.',$endDate->format('g:i a')) .'';
            $stringtoclean = str_replace(".M.arch","March", $stringtoclean);
            $stringtoclean = str_replace(".M.ay","May", $stringtoclean);
            $stringtoclean = str_replace("Septe.m.ber","September", $stringtoclean);
            $stringtoclean = str_replace("Nove.m.ber","November", $stringtoclean);
            $stringtoclean = str_replace("Dece.m.ber","December", $stringtoclean);
            $stringtoclean = str_replace(":00","", $stringtoclean);
            $stringtoclean = str_replace(",","", $stringtoclean);
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



            $html .= "</div>";

            $birmcount++;
    }}
    foreach ($responseData->data->entries as $theEvent) {
        if ($theEvent->locationName == "Heatherdowns" && $heatcount < "3") {
            $eventStartDate = new DateTime($theEvent->eventStart);
            $eventEndate = new DateTime($theEvent->eventEnd);
            if ($heattitle != "1") {
                $html .= '<h4 style="margin-bottom:5px"><a href="http://events.toledolibrary.org/events?v=grid&t=Storytime&l=Heatherdowns">' . $theEvent->locationName . '</a></h4>';
                $heattitle = "1";
            }
            $html .= '<div class="story-time-block">';
            //$html .= '' . str_replace('m', '.m.',$eventStartDate->format('D, F j | g:i a')) . ' &mdash; ' . str_replace('m', '.m.',$eventEndate->format('g:i a'));

            $startDate = new DateTime($theEvent->eventStart);
            $endDate = new DateTime($theEvent->eventEnd);

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


            $html .= "</div>";

            $heatcount++;
        }}
        foreach ($responseData->data->entries as $theEvent) {
            if ($theEvent->locationName == "Holland" && $hollcount < "3") {
                $eventStartDate = new DateTime($theEvent->eventStart);
                $eventEndate = new DateTime($theEvent->eventEnd);
                if ($holltitle != "1") {
                    $html .= '<h4 style="margin-bottom:5px"><a href="http://events.toledolibrary.org/events?v=grid&t=Storytime&l=Holland">' . $theEvent->locationName . '</a></h4>';
                    $holltitle = "1";
                }
                $html .= '<div class="story-time-block">';
                //$html .= '' . str_replace('m', '.m.',$eventStartDate->format('D, F j | g:i a')) . ' &mdash; ' . str_replace('m', '.m.',$eventEndate->format('g:i a'));

                $startDate = new DateTime($theEvent->eventStart);
                $endDate = new DateTime($theEvent->eventEnd);

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



                //$html .= $stringtocleancountam;
                //$html .= $stringtocleancountpm;

                $html .= $stringtoclean;


                //$html .= $stringtoclean;


                $html .= "</div>";

                $hollcount++;
            }}
            foreach ($responseData->data->entries as $theEvent) {
                if ($theEvent->locationName == "Kent" && $kentcount < "3") {
                    $eventStartDate = new DateTime($theEvent->eventStart);
                    $eventEndate = new DateTime($theEvent->eventEnd);
                    if ($kenttitle != "1") {
                        $html .= '<h4 style="margin-bottom:5px"><a href="http://events.toledolibrary.org/events?v=grid&t=Storytime&l=Kent">' . $theEvent->locationName . '</a></h4>';
                        $kenttitle = "1";
                    }
                    $html .= '<div class="story-time-block">';
                    //$html .= '' . str_replace('m', '.m.',$eventStartDate->format('D, F j | g:i a')) . ' &mdash; ' . str_replace('m', '.m.',$eventEndate->format('g:i a'));

                    $startDate = new DateTime($theEvent->eventStart);
                    $endDate = new DateTime($theEvent->eventEnd);

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


                    $html .= "</div>";

                    $kentcount++;
                }}
    foreach ($responseData->data->entries as $theEvent) {
        if ($theEvent->locationName == "King Road" && $kingcount < "3") {
            $eventStartDate = new DateTime($theEvent->eventStart);
            $eventEndate = new DateTime($theEvent->eventEnd);
            if ($krtitle != "1") {
                $html .= '<h4 style="margin-bottom:5px"><a href="http://events.toledolibrary.org/events?v=grid&t=Storytime&l=King%20Road">' . $theEvent->locationName . '</a></h4>';
                $krtitle = "1";
            }
            $html .= '<div class="story-time-block">';
            //$html .= '' . str_replace('m', '.m.',$eventStartDate->format('D, F j | g:i a')) . ' &mdash; ' . str_replace('m', '.m.',$eventEndate->format('g:i a'));

            $startDate = new DateTime($theEvent->eventStart);
            $endDate = new DateTime($theEvent->eventEnd);

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


            $html .= "</div>";

            $kingcount++;
        }}
    foreach ($responseData->data->entries as $theEvent) {
        if ($theEvent->locationName == "Lagrange" && $lagrcount < "3") {
            $eventStartDate = new DateTime($theEvent->eventStart);
            $eventEndate = new DateTime($theEvent->eventEnd);
            if ($lagrtitle != "1") {
                $html .= '<h4 style="margin-bottom:5px"><a href="http://events.toledolibrary.org/events?v=grid&t=Storytime&l=Lagrange">' . $theEvent->locationName . '</a></h4>';
                $lagrtitle = "1";
            }
            $html .= '<div class="story-time-block">';
            //$html .= '' . str_replace('m', '.m.',$eventStartDate->format('D, F j | g:i a')) . ' &mdash; ' . str_replace('m', '.m.',$eventEndate->format('g:i a'));

            $startDate = new DateTime($theEvent->eventStart);
            $endDate = new DateTime($theEvent->eventEnd);

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


            $html .= "</div>";

            $lagrcount++;
        }}
    foreach ($responseData->data->entries as $theEvent) {
        if ($theEvent->locationName == "Locke" && $lockecount < "3") {
            $eventStartDate = new DateTime($theEvent->eventStart);
            $eventEndate = new DateTime($theEvent->eventEnd);
            if ($locketitle != "1") {
                $html .= '<h4 style="margin-bottom:5px"><a href="http://events.toledolibrary.org/events?v=grid&t=Storytime&l=Locke">' . $theEvent->locationName . '</a></h4>';
                $locketitle = "1";
            }
            $html .= '<div class="story-time-block">';
            //$html .= '' . str_replace('m', '.m.',$eventStartDate->format('D, F j | g:i a')) . ' &mdash; ' . str_replace('m', '.m.',$eventEndate->format('g:i a'));


            $startDate = new DateTime($theEvent->eventStart);
            $endDate = new DateTime($theEvent->eventEnd);

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


            $html .= "</div>";

            $lockecount++;
        }}
        foreach ($responseData->data->entries as $theEvent) {
            if ($theEvent->locationName == "Main" && $maincount < "3") {
                $eventStartDate = new DateTime($theEvent->eventStart);
                $eventEndate = new DateTime($theEvent->eventEnd);
                if ($maintitle != "1") {
                    $html .= '<h4 style="margin-bottom:5px"><a href="http://events.toledolibrary.org/events?v=grid&t=Storytime&l=Main%20Library">' . $theEvent->locationName . '</a></h4>';
                    $maintitle = "1";
                }
                $html .= '<div class="story-time-block">';
                //$html .= '' . str_replace('m', '.m.',$eventStartDate->format('D, F j | g:i a')) . ' &mdash; ' . str_replace('m', '.m.',$eventEndate->format('g:i a'));

                $startDate = new DateTime($theEvent->eventStart);
                $endDate = new DateTime($theEvent->eventEnd);

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


                $html .= "</div>";

                $maincount++;
            }}
            foreach ($responseData->data->entries as $theEvent) {
                if ($theEvent->locationName == "Maumee" && $maumcount < "3") {
                    $eventStartDate = new DateTime($theEvent->eventStart);
                    $eventEndate = new DateTime($theEvent->eventEnd);
                    if ($maumtitle != "1") {
                        $html .= '<h4 style="margin-bottom:5px"><a href="http://events.toledolibrary.org/events?v=grid&t=Storytime&l=Maumee">' . $theEvent->locationName . '</a></h4>';
                        $maumtitle = "1";
                    }
                    $html .= '<div class="story-time-block">';
                    //$html .= '' . str_replace('m', '.m.',$eventStartDate->format('D, F j | g:i a')) . ' &mdash; ' . str_replace('m', '.m.',$eventEndate->format('g:i a'));


                    $startDate = new DateTime($theEvent->eventStart);
                    $endDate = new DateTime($theEvent->eventEnd);

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


                    $html .= "</div>";

                    $maumcount++;
                }}
                foreach ($responseData->data->entries as $theEvent) {
                    if ($theEvent->locationName == "Mott" && $mottcount < "3") {
                        $eventStartDate = new DateTime($theEvent->eventStart);
                        $eventEndate = new DateTime($theEvent->eventEnd);
                        if ($motttitle != "1") {
                            $html .= '<h4 style="margin-bottom:5px"><a href="http://events.toledolibrary.org/events?v=grid&t=Storytime&l=Mott">' . $theEvent->locationName . '</a></h4>';
                            $motttitle = "1";
                        }
                        $html .= '<div class="story-time-block">';
                        //$html .= '' . str_replace('m', '.m.',$eventStartDate->format('D, F j | g:i a')) . ' &mdash; ' . str_replace('m', '.m.',$eventEndate->format('g:i a'));

                        $startDate = new DateTime($theEvent->eventStart);
                        $endDate = new DateTime($theEvent->eventEnd);

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



                        $html .= "</div>";

                        $mottcount++;
                    }}
                    foreach ($responseData->data->entries as $theEvent) {
                        if ($theEvent->locationName == "Oregon" && $oregcount < "3") {
                            $eventStartDate = new DateTime($theEvent->eventStart);
                            $eventEndate = new DateTime($theEvent->eventEnd);
                            if ($oregtitle != "1") {
                                $html .= '<h4 style="margin-bottom:5px"><a href="http://events.toledolibrary.org/events?v=grid&t=Storytime&l=Oregon">' . $theEvent->locationName . '</a></h4>';
                                $oregtitle = "1";
                            }
                            $html .= '<div class="story-time-block">';
                            //$html .= '' . str_replace('m', '.m.',$eventStartDate->format('D, F j | g:i a')) . ' &mdash; ' . str_replace('m', '.m.',$eventEndate->format('g:i a'));

                            $startDate = new DateTime($theEvent->eventStart);
                            $endDate = new DateTime($theEvent->eventEnd);

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


                            $html .= "</div>";

                            $oregcount++;
                        }}
                        foreach ($responseData->data->entries as $theEvent) {
                            if ($theEvent->locationName == "Point Place" && $ppcount < "3") {
                                $eventStartDate = new DateTime($theEvent->eventStart);
                                $eventEndate = new DateTime($theEvent->eventEnd);
                                if ($pptitle != "1") {
                                    $html .= '<h4 style="margin-bottom:5px"><a href="http://events.toledolibrary.org/events?v=grid&t=Storytime&l=Point%20Place">' . $theEvent->locationName . '</a></h4>';
                                    $pptitle = "1";
                                }
                                $html .= '<div class="story-time-block">';
                               // $html .= '' . str_replace('m', '.m.',$eventStartDate->format('D, F j | g:i a')) . ' &mdash; ' . str_replace('m', '.m.',$eventEndate->format('g:i a'));

                               $startDate = new DateTime($theEvent->eventStart);
                               $endDate = new DateTime($theEvent->eventEnd);

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


                                $html .= "</div>";

                                $ppcount++;
                            }}
                            foreach ($responseData->data->entries as $theEvent) {
                                if ($theEvent->locationName == "Reynolds Corners" && $reyccount < "3") {
                                    $eventStartDate = new DateTime($theEvent->eventStart);
                                    $eventEndate = new DateTime($theEvent->eventEnd);
                                    if ($reyctitle != "1") {
                                        $html .= '<h4 style="margin-bottom:5px"><a href="http://events.toledolibrary.org/events?v=grid&t=Storytime&l=Reynolds%20Corners">' . $theEvent->locationName . '</a></h4>';
                                        $reyctitle = "1";
                                    }
                                    $html .= '<div class="story-time-block">';
                                    //$html .= '' . str_replace('m', '.m.',$eventStartDate->format('D, F j | g:i a')) . ' &mdash; ' . str_replace('m', '.m.',$eventEndate->format('g:i a'));

                                    $startDate = new DateTime($theEvent->eventStart);
                                    $endDate = new DateTime($theEvent->eventEnd);

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



                                    $html .= "</div>";

                                    $reyccount++;
                                }}
                                foreach ($responseData->data->entries as $theEvent) {
                                    if ($theEvent->locationName == "Sanger" && $sangcount < "3") {
                                        $eventStartDate = new DateTime($theEvent->eventStart);
                                        $eventEndate = new DateTime($theEvent->eventEnd);
                                        if ($sangtitle != "1") {
                                            $html .= '<h4 style="margin-bottom:5px"><a href="http://events.toledolibrary.org/events?v=grid&t=Storytime&l=Sanger">' . $theEvent->locationName . '</a></h4>';
                                            $sangtitle = "1";
                                        }
                                        $html .= '<div class="story-time-block">';
                                        //$html .= '' . str_replace('m', '.m.',$eventStartDate->format('D, F j | g:i a')) . ' &mdash; ' . str_replace('m', '.m.',$eventEndate->format('g:i a'));

                                        $startDate = new DateTime($theEvent->eventStart);
                                        $endDate = new DateTime($theEvent->eventEnd);

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



                                        $html .= "</div>";

                                        $sangcount++;
                                    }}
                        foreach ($responseData->data->entries as $theEvent) {
                            if ($theEvent->locationName == "South" && $southcount < "3") {
                                $eventStartDate = new DateTime($theEvent->eventStart);
                                $eventEndate = new DateTime($theEvent->eventEnd);
                                if ($souttitle != "1") {
                                    $html .= '<h4 style="margin-bottom:5px"><a href="http://events.toledolibrary.org/events?v=grid&t=Storytime&l=South">' . $theEvent->locationName . '</a></h4>';
                                    $souttitle = "1";
                                }
                                $html .= '<div class="story-time-block">';
                                //$html .= '' . str_replace('m', '.m.',$eventStartDate->format('D, F j | g:i a')) . ' &mdash; ' . str_replace('m', '.m.',$eventEndate->format('g:i a'));

                                $startDate = new DateTime($theEvent->eventStart);
                                $endDate = new DateTime($theEvent->eventEnd);

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




                                $html .= "</div>";

                                $southcount++;
                            }}
                        foreach ($responseData->data->entries as $theEvent) {
                            if ($theEvent->locationName == "Sylvania" && $sylvcount < "3") {
                                $eventStartDate = new DateTime($theEvent->eventStart);
                                $eventEndate = new DateTime($theEvent->eventEnd);
                                if ($sylvtitle != "1") {
                                    $html .= '<h4 style="margin-bottom:5px"><a href="http://events.toledolibrary.org/events?v=grid&t=Storytime&l=Sylvania">' . $theEvent->locationName . '</a></h4>';
                                    $sylvtitle = "1";
                                }
                                $html .= '<div class="story-time-block">';
                                //$html .= '' . str_replace('m', '.m.',$eventStartDate->format('D, F j | g:i a')) . ' &mdash; ' . str_replace('m', '.m.',$eventEndate->format('g:i a'));

                                $startDate = new DateTime($theEvent->eventStart);
                                $endDate = new DateTime($theEvent->eventEnd);

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



                                $html .= "</div>";

                                $sylvcount++;

                                }
                            }
                        foreach ($responseData->data->entries as $theEvent) {
                            if ($theEvent->locationName == "Toledo Heights" && $tolhcount < "3") {
                                $eventStartDate = new DateTime($theEvent->eventStart);
                                $eventEndate = new DateTime($theEvent->eventEnd);
                                if ($tolhtitle != "1") {
                                    $html .= '<h4 style="margin-bottom:5px"><a href="http://events.toledolibrary.org/events?v=grid&t=Storytime&l=Toledo%20Heights">' . $theEvent->locationName . '</a></h4>';
                                    $tolhtitle = "1";
                                }
                                $html .= '<div class="story-time-block">';
                                //$html .= '' . str_replace('m', '.m.',$eventStartDate->format('D, F j | g:i a')) . ' &mdash; ' . str_replace('m', '.m.',$eventEndate->format('g:i a'));

                                $startDate = new DateTime($theEvent->eventStart);
                                $endDate = new DateTime($theEvent->eventEnd);

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


                                $html .= "</div>";

                                $tolhcount++;

                                }
                            }
                            foreach ($responseData->data->entries as $theEvent) {
                                if ($theEvent->locationName == "Washington" && $washcount < "3") {
                                    $eventStartDate = new DateTime($theEvent->eventStart);
                                    $eventEndate = new DateTime($theEvent->eventEnd);
                                    if ($washtitle != "1") {
                                        $html .= '<h4 style="margin-bottom:5px"><a href="http://events.toledolibrary.org/events?v=grid&t=Storytime&l=Washington">' . $theEvent->locationName . '</a></h4>';
                                        $washtitle = "1";
                                    }
                                    $html .= '<div class="story-time-block">';
                                    //$html .= '' . str_replace('m', '.m.',$eventStartDate->format('D, F j | g:i a')) . ' &mdash; ' . str_replace('m', '.m.',$eventEndate->format('g:i a'));

                                    $startDate = new DateTime($theEvent->eventStart);
                                    $endDate = new DateTime($theEvent->eventEnd);

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



                                    $html .= "</div>";

                                    $washcount++;

                                    }
                                }
                                foreach ($responseData->data->entries as $theEvent) {
                                    if ($theEvent->locationName == "Waterville" && $watvcount < "3") {
                                        $eventStartDate = new DateTime($theEvent->eventStart);
                                        $eventEndate = new DateTime($theEvent->eventEnd);
                                        if ($watetitle != "1") {
                                            $html .= '<h4 style="margin-bottom:5px"><a href="http://events.toledolibrary.org/events?v=grid&t=Storytime&l=Waterville">' . $theEvent->locationName . '</a></h4>';
                                            $watetitle = "1";
                                        }
                                        $html .= '<div class="story-time-block">';
                                        //$html .= '' . str_replace('m', '.m.',$eventStartDate->format('D, F j | g:i a')) . ' &mdash; ' . str_replace('m', '.m.',$eventEndate->format('g:i a'));


                                        $startDate = new DateTime($theEvent->eventStart);
                                        $endDate = new DateTime($theEvent->eventEnd);

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


                                        $html .= "</div>";

                                        $watvcount++;

                                        }
                                    }
                                    foreach ($responseData->data->entries as $theEvent) {
                                        if ($theEvent->locationName == "West Toledo" && $westcount < "3") {
                                            $eventStartDate = new DateTime($theEvent->eventStart);
                                            $eventEndate = new DateTime($theEvent->eventEnd);
                                            if ($westtitle != "1") {
                                                $html .= '<h4 style="margin-bottom:5px"><a href="http://events.toledolibrary.org/events?v=grid&t=Storytime&l=Reynolds%20Corners">' . $theEvent->locationName . '</a></h4>';
                                                $westtitle = "1";
                                            }
                                            $html .= '<div class="story-time-block">';
                                            //$html .= '' . str_replace('m', '.m.',$eventStartDate->format('D, F j | g:i a')) . ' &mdash; ' . str_replace('m', '.m.',$eventEndate->format('g:i a'));

                                            $startDate = new DateTime($theEvent->eventStart);
                                            $endDate = new DateTime($theEvent->eventEnd);

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



                                            $html .= "</div>";

                                            $westcount++;

                                            }
                                        }

        }

// SEARCH FOR TITLE AS TITLE //
         if ($atts['formatstyle'] == "shortDescription") {


            $responseData = json_decode($response);
            

            if (empty($responseData)) {
               $html = 'We currently do not have any programs scheduled at this time. Please check back soon.';
           }
           else {

           foreach ($responseData->data->entries as $entry) {

                   if ($entry->modified != 'canceled' and $entry->modified !='rescheduled' ) :
                       $displayedevents = 1;
                       $i++;
                       $modifiedTitle = str_replace($atts['removeText'], '', $entry->title);
                       $html .= '<div class="book-group-event">';

                            if ($entry->featuredImage != null) {
                               
                               $html .=  '<div class="book-group-event-image" data-image="featuredImage for ' . $atts['removeText'] . $modifiedTitle . '" style="background-image: url(' . $entry->featuredImage . ')"></div>';
                            }
                            elseif ($entry->eventImage != null) {
                          
                               $html .=  '<div class="book-group-event-image" data-image="eventImage for ' . $atts['removeText'] . $modifiedTitle . '" style="background-image: url(' . $entry->eventImage . ')"></div></a>';
                            }
                            //endif;
                           $html .= '<div class="book-group-event-info">
                               <h3 class="book-group-event-title">';

                               if ($entry->featuredImage != null || $entry->eventImage != null) {

                                   $html .= '<a href="http://events.toledolibrary.org/event/' . $entry->eventId . '">'.$modifiedTitle.'</a>';

                                   //$html .= $entry->title;
                                   //$html .= $entry->subTitle;
                               }

                               else {
                                       if ($entry->title) {

                                       $html .= '<a href="http://events.toledolibrary.org/event/' . $entry->eventId . '">'.$modifiedTitle.'</a>';

                                       //$html .=  $entry->title;
                                   }
                               }
                               $html .= '
                               </h3>';
                               if ($entry->subTitle) {

                                $html .= '<p class="event-info-date" style="margin-top:-.5em;margin-bottom:-.5em;">';
                                   $html .=  $entry->subTitle;
                                $html .= '</p>';
                               
                               }

                               $html .= '
                               
                               <p class="event-info-date" style="font-weight:bold !important;margin-top:1em;">
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
                                   <p class="descriptionshortcommunico">
                                   ';
                                       $html .=  $entry->shortDescription;
                                   $html .= '
                                   </p>
                               </div>
                               ';
                               if ($entry->eventRegistrationUrl || $entry->registration == true) {
                                if ($entry->totalRegistrants == $entry->maxAttendees && $entry->thirdPartyRegistration == false && $entry->waitlist == false) {
                                    $html .= '<a class="woof program-is-full fusion-button button-flat fusion-button-default-size button-default button-7 fusion-button-default-span fusion-button-default-type program-is-full">FULL</a>';
                                } elseif ($entry->eventId == "9967180") {
                                    $html .= '<a class="woof2 program-is-full fusion-button button-flat fusion-button-default-size button-default button-7 fusion-button-default-span fusion-button-default-type program-is-full">FULL</a>';
                                } 
                                elseif ($entry->eventId == "11487421") {
                                    $html .= '<a class="woof2 program-is-full fusion-button button-flat fusion-button-default-size button-default button-7 fusion-button-default-span fusion-button-default-type program-is-full">FULL</a>';
                                } 
                                elseif ($entry->eventId == "11487318") {
                                    $html .= '<a class="woof2 program-is-full fusion-button button-flat fusion-button-default-size button-default button-7 fusion-button-default-span fusion-button-default-type program-is-full">FULL</a>';
                                } 
                                elseif ($entry->totalRegistrants >= $entry->maxAttendees && $entry->thirdPartyRegistration == false && $entry->waitlist == true) {
                                    $html .= '<a class="meow program-is-full fusion-button button-flat fusion-button-default-size button-default button-7 fusion-button-default-span fusion-button-default-type program-is-full" href="https://events.toledolibrary.org/event/' . $entry->eventId . '">Full - join waitlist</a>';
                                } elseif (($entry->maxAttendees - $entry->totalRegistrants) <= 9 && ($entry->maxAttendees - $entry->totalRegistrants) > 0) {
                                    $remainingSeats = $entry->maxAttendees - $entry->totalRegistrants;
                                    $html .= '<a class="fusion-button button-flat fusion-button-default-size button-default button-7 fusion-button-default-span fusion-button-default-type" href="https://events.toledolibrary.org/event/' . $entry->eventId . '">Register - '. $remainingSeats . ' spots left</a>';
                                } elseif ($entry->thirdPartyRegistration == "true") {
                                    $html .= '<a class="help fusion-button button-flat fusion-button-default-size button-default button-7 fusion-button-default-span fusion-button-default-type" href="'. $entry->eventRegistrationUrl. '">Register</a>';
                                    }
                                else {
                                    $html .= '<a class="else fusion-button button-flat fusion-button-default-size button-default button-7 fusion-button-default-span fusion-button-default-type" href="https://events.toledolibrary.org/event/' . $entry->eventId . '">Register</a>';
                               }
                            } else {
                                $html .= '<a class="fusion-button button-flat fusion-button-default-size button-default button-7 fusion-button-default-span fusion-button-default-type" href="https://events.toledolibrary.org/event/' . $entry->eventId . '">Read More</a>';
                            }
                           $html .= '</div>
                       </div>
                   ';   
                   endif;   
                   
                   
                         

           }

                   if ($displayedevents == 0) {
                       $html = 'We currently do not have any programs scheduled at this time. Please check back soon.';
                   }
           
           

           }    
           //print('why i no display stuff');

       
      
       return $html;

        }


// SEARCH FOR CALENDAR //

         if ($atts['formatstyle'] == "calendar") {

$responseData = json_decode($response);

if (empty($responseData)) {
    $html = 'We currently do not have any programs scheduled at this time. Please check back soon.';
} elseif (isset($atts['formatstyle']) && $atts['formatstyle'] == "calendar") {
    // Sort the entries by eventStart
    usort($responseData->data->entries, function ($a, $b) {
        return strtotime($a->eventStart) - strtotime($b->eventStart);
    });

    $calendarEntries = [];

    foreach ($responseData->data->entries as $entry) {
        if ($entry->modified != 'canceled' and $entry->modified != 'rescheduled') {
            $startDate = new DateTime($entry->eventStart);
            $dateKey = $startDate->format('Y-m-d');

            $calendarEntries[$dateKey][] = $entry;
        }
    }

    // Table header
    $html .= '<table class="calendar-table">';
    $html .= '<thead>';
    $html .= '<tr>';
    // Header row for days and dates (shortened to 3 characters, from Sun to Sat)
    for ($i = 0; $i < 7; $i++) {
        $currentDate = new DateTime('today');
        $currentDate->modify('+' . $i . ' days');
        $html .= '<th>' . $currentDate->format('D, M j') . '</th>';
    }
    $html .= '</tr>';
    $html .= '</thead>';
    $html .= '<tbody>';

    // Iterate over 24 hours
    for ($hour = 0; $hour < 24; $hour++) {

         // Skip rows for 12am-8am and 9pm-12am
        if (($hour >= 0 && $hour < 8) || ($hour >= 21 && $hour < 24)) {
            continue;
        }


        $html .= '<tr>';
        // Iterate over 7 days (from Sunday to Saturday)
        for ($i = 0; $i < 7; $i++) {
            $currentDate = new DateTime('today');
            $currentDate->modify('+' . $i . ' days');
            $dateKey = $currentDate->format('Y-m-d');

            $html .= '<td>';
            if (isset($calendarEntries[$dateKey])) {
                foreach ($calendarEntries[$dateKey] as $entry) {
                    $startDate = new DateTime($entry->eventStart);
                    if ($startDate->format('G') == $hour) {
                        // Display your entry content here as needed
                        $html .= '<div class="book-group-event">';
                        $html .= '<p class="event-info-date"><a href="http://events.toledolibrary.org/event/' . $entry->eventId . '">' . $entry->title . '</a></p>';
                        $html .= '
                            <p class="event-info-date" style="font-weight:bold !important;">
                                    ';
                                    if ($entry->eventStart) {
                                        $startDate = new DateTime($entry->eventStart);
                                        $endDate = new DateTime($entry->eventEnd);

                                        $stringtoclean = $startDate->format('g:i a') . ' &mdash; ' . $endDate->format('g:i a');
                                        $stringtoclean = str_replace(",","", $stringtoclean);
                                        $stringtoclean = str_replace(":00","", $stringtoclean);
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
                        ';
                        $html .= '</div>';
                    }
                }
            }
            $html .= '</td>';
        }
        $html .= '</tr>';
    }

    $html .= '</tbody>';

    $html .= '</table>';
}
 $html .= '

<script>
 //   document.addEventListener("DOMContentLoaded", function() {
 //       // Select all <td> elements in the document
 //       var tdElements = document.querySelectorAll("td");
 //
 //       // Iterate through each <td> element
 //       tdElements.forEach(function(td) {
 //           // Check if the <td> content is empty
 //           if (td.innerHTML.trim() === "") {
 //               // Remove the empty <td> element
 //               td.parentNode.removeChild(td);
 //           }
 //       });
 //   });
</script>



 <style>

 .calendar-table {
    width:100%;
    border-bottom: solid black 0.15em;
 }

  .calendar-table th {
    border: solid black .15em;
    padding: 2em;
 }

  .calendar-table tr {

 }

  .calendar-table td {
     border-left: solid black .15em;
     border-right: solid black .15em;
     padding:1em;
 }

 .book-group-event {
    flex-direction: column;
    margin-bottom: 1emrem;
}

.book-group-event .post-content p {
    margin-bottom:0;
    }

.hide-row {
    display:none;
    }

 </style>

';
        }

// SEARCH FOR SUBTITLE //

        if ($atts['formatstyle'] == "subtitle") {


            


            $responseData = json_decode($response);
            

            if (empty($responseData)) {
               $html = 'We currently do not have any programs scheduled at this time. Please check back soon.';
           }
           else {

           foreach ($responseData->data->entries as $entry) {
                    //if ($entry->modified != 'canceled' and $entry->modified !='rescheduled' and $entry->subTitle !='' ) :
                   if ($entry->modified != 'canceled' and $entry->modified !='rescheduled' ) :
                       $displayedevents = 1;
                       $i++;
                       $html .= '<div class="book-group-event">';
                            if ($entry->featuredImage != null) {
                               $html .=  '<div class="book-group-event-image" data-image="featuredImage for ' . $entry->title . '" style="background-image: url(' . $entry->featuredImage . ')"></div>';
                            }
                            elseif ($entry->eventImage != null) {
                               $html .=  '<div class="book-group-event-image" data-image="eventImage for ' . $entry->title . '" style="background-image: url(' . $entry->eventImage . ')"></div></a>';
                            }
                            //endif;
                           $html .= '<div class="book-group-event-info">
                               <h3 class="book-group-event-title">';

                               if ($entry->featuredImage != null || $entry->eventImage != null) {

                                   $html .= '<a href="http://events.toledolibrary.org/event/' . $entry->eventId . '">'.$entry->subTitle.'</a>';

                                   //$html .= $entry->title;
                                   //$html .= $entry->subTitle;
                               }

                               else {
                                       if ($entry->title) {

                                       $html .= '<a href="http://events.toledolibrary.org/event/' . $entry->eventId . '">'.$entry->subTitle.'</a>';

                                       //$html .=  $entry->title;
                                   }
                               }
                               $html .= '
                               </h3>
                               <p class="event-info-date" style="font-weight:bold !important;margin-top:1em;">
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
                                    <p class="diffmarginprograms" style="margin-top:-1.25em">
                                   ';
                                       $html .=  $entry->shortDescription;
                                   $html .= '
                                   </p>
                               </div>
                               ';
                               // new button text for registration limit full so many remaining

                               


                               if ($entry->eventRegistrationUrl || $entry->registration == true) {
                                if ($entry->totalRegistrants == $entry->maxAttendees && $entry->thirdPartyRegistration == false && $entry->waitlist == false) {
                                    $html .= '<a class="woof program-is-full fusion-button button-flat fusion-button-default-size button-default button-7 fusion-button-default-span fusion-button-default-type program-is-full">FULL</a>';
                                } elseif ($entry->totalRegistrants >= $entry->maxAttendees && $entry->thirdPartyRegistration == false && $entry->waitlist == true) {
                                    $html .= '<a class="meow program-is-full fusion-button button-flat fusion-button-default-size button-default button-7 fusion-button-default-span fusion-button-default-type program-is-full" href="https://events.toledolibrary.org/event/' . $entry->eventId . '">Full - join waitlist</a>';
                                } elseif (($entry->maxAttendees - $entry->totalRegistrants) <= 9 && ($entry->maxAttendees - $entry->totalRegistrants) > 0) {
                                    $remainingSeats = $entry->maxAttendees - $entry->totalRegistrants;
                                    $html .= '<a class="fusion-button button-flat fusion-button-default-size button-default button-7 fusion-button-default-span fusion-button-default-type" href="http://events.toledolibrary.org/event/' . $entry->eventId . '">Register - '. $remainingSeats . ' spots left</a>';
                                } elseif ($entry->thirdPartyRegistration == "true") {
                                    $html .= '<a class="fusion-button button-flat fusion-button-default-size button-default button-7 fusion-button-default-span fusion-button-default-type" href="'. $entry->eventRegistrationUrl. '">Register</a>';
                                    }
                                else {
                                    $html .= '<a class="fusion-button button-flat fusion-button-default-size button-default button-7 fusion-button-default-span fusion-button-default-type" href="http://events.toledolibrary.org/event/' . $entry->eventId . '">Register</a>';
                               }
                            } else {
                                $html .= '<a class="fusion-button button-flat fusion-button-default-size button-default button-7 fusion-button-default-span fusion-button-default-type" href="http://events.toledolibrary.org/event/' . $entry->eventId . '">Read More</a>';
                            }




                            
                           $html .= '</div>
                       </div>
                   ';   
                   endif;   
                   
                   
                         

           }

                   if ($displayedevents == 0) {
                       $html = 'We currently do not have any programs scheduled at this time. Please check back soon.';
                   }
           
           

           }    
           //print('why i no display stuff');

       
      
       return $html;

        }

// SEARCH FOR FAIL SAFE //

             if ($atts['formatstyle'] != "storytime" && $atts['formatstyle'] != "shortDescription" && $atts['formatstyle'] != "calendar" && $atts['formatstyle'] != "subtitle" && $atts['formatstyle'] != "imageDescription") {



                $responseData = json_decode($response);
            

                if (empty($responseData)) {
                   $html = 'We currently do not have any programs scheduled at this time. Please check back soon.';
               }
               else {
    
               foreach ($responseData->data->entries as $entry) {
    
                       if ($entry->modified != 'canceled' and $entry->modified !='rescheduled' ) :
                           $displayedevents = 1;
                           $i++;
                           $modifiedTitle = str_replace($atts['removeText'], '', $entry->title);
                           $html .= '<div class="book-group-event">';
    
                                if ($entry->featuredImage != null) {
                                   
                                   $html .=  '<div class="book-group-event-image" data-image="featuredImage for ' . $atts['removeText'] . $modifiedTitle . '" style="background-image: url(' . $entry->featuredImage . ')"></div>';
                                }
                                elseif ($entry->eventImage != null) {
                              
                                   $html .=  '<div class="book-group-event-image" data-image="eventImage for ' . $atts['removeText'] . $modifiedTitle . '" style="background-image: url(' . $entry->eventImage . ')"></div></a>';
                                }
                                //endif;
                               $html .= '<div class="book-group-event-info">
                                   <h3 class="book-group-event-title">';
    
                                   if ($entry->featuredImage != null || $entry->eventImage != null) {
    
                                       $html .= '<a href="http://events.toledolibrary.org/event/' . $entry->eventId . '">'.$modifiedTitle.'</a>';
    
                                       //$html .= $entry->title;
                                       //$html .= $entry->subTitle;
                                   }
    
                                   else {
                                           if ($entry->title) {
    
                                           $html .= '<a href="http://events.toledolibrary.org/event/' . $entry->eventId . '">'.$modifiedTitle.'</a>';
    
                                           //$html .=  $entry->title;
                                       }
                                   }
                                   $html .= '
                                   </h3>';
                                   if ($entry->subTitle) {
    
                                    $html .= '<p class="event-info-date" style="margin-top:-.5em;margin-bottom:-.5em;">';
                                       $html .=  $entry->subTitle;
                                    $html .= '</p>';
                                   
                                   }
    
                                   $html .= '
                                   
                                   <p class="event-info-date" style="font-weight:bold !important;margin-top:1em;">
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
                                       <p style="margin-top:-1.25em">
                                       ';
                                           $html .=  $entry->shortDescription;
                                       $html .= '
                                       </p>
                                   </div>
                                   ';


                                   if ($entry->eventRegistrationUrl || $entry->registration == true) {
                                    if ($entry->totalRegistrants == $entry->maxAttendees && $entry->thirdPartyRegistration == false && $entry->waitlist == false) {
                                        $html .= '<a class="woof program-is-full fusion-button button-flat fusion-button-default-size button-default button-7 fusion-button-default-span fusion-button-default-type program-is-full">FULL</a>';
                                    } elseif ($entry->totalRegistrants >= $entry->maxAttendees && $entry->thirdPartyRegistration == false && $entry->waitlist == true) {
                                        $html .= '<a class="meow program-is-full fusion-button button-flat fusion-button-default-size button-default button-7 fusion-button-default-span fusion-button-default-type program-is-full" href="https://events.toledolibrary.org/event/' . $entry->eventId . '">Full - join waitlist</a>';
                                    } elseif (($entry->maxAttendees - $entry->totalRegistrants) <= 9 && ($entry->maxAttendees - $entry->totalRegistrants) > 0) {
                                        $remainingSeats = $entry->maxAttendees - $entry->totalRegistrants;
                                        $html .= '<a class="fusion-button button-flat fusion-button-default-size button-default button-7 fusion-button-default-span fusion-button-default-type" href="http://events.toledolibrary.org/event/' . $entry->eventId . '">Register - '. $remainingSeats . ' spots left</a>';
                                    } elseif ($entry->thirdPartyRegistration == "true") {
                                        $html .= '<a class="fusion-button button-flat fusion-button-default-size button-default button-7 fusion-button-default-span fusion-button-default-type" href="'. $entry->eventRegistrationUrl. '">Register</a>';
                                        }
                                    else {
                                        $html .= '<a class="fusion-button button-flat fusion-button-default-size button-default button-7 fusion-button-default-span fusion-button-default-type" href="http://events.toledolibrary.org/event/' . $entry->eventId . '">Register</a>';
                                   }
                                } else {
                                    $html .= '<a class="fusion-button button-flat fusion-button-default-size button-default button-7 fusion-button-default-span fusion-button-default-type" href="http://events.toledolibrary.org/event/' . $entry->eventId . '">Read More</a>';
                                }


                               $html .= '</div>
                           </div>
                       ';   
                       endif;   
                       
                       
                             
    
               }
    
                       if ($displayedevents == 0) {
                           $html = 'We currently do not have any programs scheduled at this time. Please check back soon.';
                       }
               
               
    
               }    
               //print('why i no display stuff');
    
           
          
           return $html;
    

        }


       
        return $html;
    }
        }

    private function getCommunicoDataFromAPI($data) {
        $startDate = date('Y-m-d');
        $endDate = date('Y-m-d', strtotime('+365days'));
        $url = 'https://api.communico.co/v3/attend/events?limit=1500&status=published&privateEvents=false&fields=eventType,types,ages,reportingCategory,eventRegistrationUrl,waitlist,registration,featuredImage,eventImage,searchTags,totalRegistrants,maxAttendees,thirdPartyRegistration&startDate=' . $startDate . '&endDate=' . $endDate .$data;
        $args = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->access_token
            ),
            //'param' => $data
        );

        //print_r($url);
        //print_r($args);

        $response = wp_remote_get($url, $args);

        //print_r($response);

        if (!is_wp_error($response)) {
            return wp_remote_retrieve_body($response);
            //return $response;
        }

        return null;
    }
}

new CommunicoDataPuller();
?>