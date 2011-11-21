<?php
/*
 * course.php - course controller
 * Copyright (c) 2010  Andr� Kla�en
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License as
 * published by the Free Software Foundation; either version 2 of
 * the License, or (at your option) any later version.
 */

require_once 'app/controllers/studip_controller.php';
require_once $this->trails_root.'/models/OCRestClient.php';
require_once $this->trails_root.'/models/OCModel.php';

class CourseController extends StudipController
{
    /**
     * Common code for all actions: set default layout and page title.
     */
    function before_filter(&$action, &$args)
    {
        $this->flash = Trails_Flash::instance();

        // set default layout
        $layout = $GLOBALS['template_factory']->open('layouts/base');
        $this->set_layout($layout);

        $GLOBALS['CURRENT_PAGE'] = $_SESSION['SessSemName'][0] . ' - Opencast Player';
        
        if(($this->search_conf = OCRestClient::getConfig('search')) && ($this->series_conf = OCRestClient::getConfig('schedule'))
                && ($this->scheduler_conf = OCRestClient::getConfig('series'))) {
            $this->series_client = new OCRestClient($this->series_conf['service_url'], $this->series_conf['service_user'], $this->series_conf['service_password']);
            $this->search_client = new OCRestClient($this->search_conf['service_url'], $this->search_conf['service_user'], $this->search_conf['service_password']);
            $this->scheduler_client = new OCRestClient($this->scheduler_conf['service_url'], $this->scheduler_conf['service_user'], $this->scheduler['service_password']);
        } elseif (!$this->search_client->getAllSeries()) {
             $this->flash['error'] = _("Es besteht momentan keine Verbindung zum Search Service");
        } else {
            throw new Exception(_("Die Verkn�pfung  zum Opencast Matterhorn Server wurde nicht korrekt durchgef�hrt."));
        }
    }

    /**
     * This is the default action of this controller.
     */
    function index_action($active_id = '')
    {
        Navigation::activateItem('course/opencast/overview');
        $navigation = Navigation::getItem('/course/opencast');
        $navigation->setImage('../../'.$this->dispatcher->trails_root.'/images/oc-logo-black.png');




        if (isset($this->flash['message'])) {
            $this->message = $this->flash['message'];
        }
        $this->course_id = $_SESSION['SessionSeminar'];

        // lets get all episodes for the connected series
        if ($cseries = OCModel::getConnectedSeries($this->course_id) && !isset($this->flash['error'])) {
            $cseries = OCModel::getConnectedSeries($this->course_id);
           
            $this->episode_ids = array();
            $ids = array();
            $count = 0;
            foreach(OCModel::getConnectedSeries($this->course_id) as $serie) {
                
                 if ($series[] = $this->search_client->getEpisode($serie['series_id'])){
                     $x = 'search-results';
           
                     if($series[0]->$x->total > 0) {
                         $has_episodes = true;

                         foreach($series[0]->$x->result as $episode) {
                            $visible = OCModel::getVisibilityForEpisode($this->course_id, $episode->id);
                            if(is_object($episode->mediapackage) && $visible['visible'] == 'true') {
                                $count++;
                                $ids[] = $episode->id;
                                $this->episode_ids[] = array('id' => $episode->id,
                                                                'title' => $episode->dcTitle,
                                                                'start' => $episode->mediapackage->start,
                                                                'duration' => $episode->mediapackage->duration,
                                                                'description' => ''
                                                           );
                                }
                         }
                     } else {
                         $has_episodes = false;
                     }

                 }  else {
                    $this->flash['error'] = _("Es besteht momentan keine Verbindung zum Series Service");
                 }
            }
            
            if($active_id) {
                $this->active_id = $active_id;
            } else {
                $this->active_id = $this->episode_ids[0][id];
            }
            if($has_episodes && $count > 0) {
                $this->embed = $this->search_conf['service_url'] ."/engage/ui/embed.html?id=".$this->active_id;
            }
        }
    }
    
    function config_action()
    {
        require_once 'lib/raumzeit/raumzeit_functions.inc.php';
        if (isset($this->flash['message'])) {
            $this->message = $this->flash['message'];
        }
        
        Navigation::activateItem('course/opencast/config');
        $navigation = Navigation::getItem('/course/opencast');
        $navigation->setImage('../../'.$this->dispatcher->trails_root.'/images/oc-logo-black.png');


        
        $this->course_id = $_SESSION['SessionSeminar'];
        $this->series = $this->series_client->getAllSeries();
        
        //$this->series = OCModel::getUnconnectedSeries();


        $this->cseries = OCModel::getConnectedSeries($this->course_id);
        //$this->rseries = array_diff($this->series, $this->cseries);
        $this->rseries = $this->series;

        
        


        
        $sem = new Seminar($this->course_id);

        
         if(count($this->cseries) > 0){
             $this->connected = false;
             $serie= $this->cseries;
             $serie = array_pop($serie);
             //var_dump($serie); die;
             
             if($serie['schedule'] == 1){
                $this->dates  = OCModel::getDates($this->course_id);
             } else {
                 $this->connected = true;
                 if ($series[] = $this->search_client->getEpisode($serie['series_id'])){
                     $x = 'search-results';

                     if($series[0]->$x->total > 0) {
                         $this->episodes = $series[0]->$x->result;
                     }                    
                 }
             }
         }
    }
    
    function edit_action($course_id)
    {   
        $series = Request::getArray('series');
        foreach( $series as $serie) {
            OCModel::setSeriesforCourse($course_id, $serie);
        }
        $this->flash['message'] = _("�nderungen wurden erflolgreich �bernommen");
        $this->redirect(PluginEngine::getLink('opencast/course/config'));
    }
    
    function remove_series_action($series_id)
    {
        $course_id = Request::get('cid');
        OCModel::removeSeriesforCourse($course_id, $series_id);
        $this->flash['message'] = _("Zuordnung wurde entfernt");
        $this->redirect(PluginEngine::getLink('opencast/course/config'));
    }

    function schedule_action($resource_id, $termin_id)
    {

        $this->course_id = Request::get('cid');

        if($this->scheduler_client->scheduleEventForSeminar($this->course_id, $resource_id, $termin_id)) {
            $this->flash['message'] = _("Aufzeichnung wurde geplant.");
        } else {
            $this->flash['error'] = _("Aufzeichnung konnte nicht geplant werden.");
        }

        
        $this->redirect(PluginEngine::getLink('opencast/course/config'));
    }

    function unschedule_action($resource_id, $termin_id)
    {

        $this->course_id = Request::get('cid');

        if( $this->scheduler_client->deleteEventForSeminar($this->course_id, $resource_id, $termin_id)) {
            $this->flash['message'] = _("Die geplante Aufzeichnung wurde entfernt");
        } else {
            $this->flash['error'] = _("Die geplante Aufzeichnung konnte nicht entfernt werden.");
        }


        $this->redirect(PluginEngine::getLink('opencast/course/config'));
    }


    function update_action($resource_id, $termin_id)
    {

        $this->course_id = Request::get('cid');

        if( $this->scheduler_client->updateEventForSeminar($this->course_id, $resource_id, $termin_id)) {
            $this->flash['message'] = _("Die geplante Aufzeichnung aktualisiert");
        } else {
            $this->flash['error'] = _("Die geplante Aufzeichnung konnte nicht aktualisiert werden.");
        }


        $this->redirect(PluginEngine::getLink('opencast/course/config'));
    }


    function create_series_action()
    {
        $this->course_id = Request::get('cid');

        if($this->series_client->createSeriesForSeminar($this->course_id)) {
            $this->flash['message'] = _("Series wurde angelegt");
            $this->redirect(PluginEngine::getLink('opencast/course/config'));
        } else {
            throw new Exception("Verbindung zum Series-Service konnte nicht hergestellt werden.");
        }
    }

    function toggle_visibility_action($episode_id) {
        $this->course_id = Request::get('cid');
     
        $visible = OCModel::getVisibilityForEpisode($this->course_id, $episode_id);

        if($visible['visible'] == 'true'){
           OCModel::setVisibilityForEpisode($this->course_id, $episode_id, 'false');
           $this->flash['message'] = _("Episode wurde unsichtbar geschaltet");
           $this->redirect(PluginEngine::getLink('opencast/course/config'));
        } else {
           OCModel::setVisibilityForEpisode($this->course_id, $episode_id, 'true');
           $this->flash['message'] = _("Episode wurde sichtbar geschaltet");
           $this->redirect(PluginEngine::getLink('opencast/course/config'));
        }
    }


}
?>
