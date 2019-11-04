<?php

require_once __DIR__.'/../bootstrap.php';

class RefreshScheduledEvents extends CronJob
{

    /**
     * Return the name of the cronjob.
     */
    public static function getName()
    {
        return _('Opencast - "Scheduled-Events-Refresh"');
    }

    /**
     * Return the description of the cronjob.
     */
    public static function getDescription()
    {
        return _('Opencast: Aktualisiert alle geplanten Aufzeichnungen der in Stud.IP verbundenen Serien.');
    }

    /**
     * Execute the cronjob.
     *
     * @param mixed $last_result What the last execution of this cronjob
     *                           returned.
     * @param Array $parameters Parameters for this cronjob instance which
     *                          were defined during scheduling.
     */
    public function execute($last_result, $parameters = array())
    {
        require_once __DIR__ .'/../classes/OCRestClient/SchedulerClient.php';

        $stmt = DBManager::get()->prepare("SELECT oc.*, oss.seminar_id, oss.series_id
            FROM oc_scheduled_recordings oc
            LEFT JOIN oc_seminar_series oss USING (seminar_id)
            JOIN termine t ON (termin_id = date_id)
            WHERE oss.schedule = 1
                AND t.date >= UNIX_TIMESTAMP()");
        $stmt->execute(array());

        $scheduled_events  = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo 'Zu aktualisierende Events: ' . sizeof($scheduled_events) . "\n";

        // TODO: consider multiple opencast installations
        $api_client = ApiEventsClient::getInstance(1);
        $events = $api_client->getAllScheduledEvents($se['series_id']);
        print_r($events);

        if (!empty($scheduled_events)) {
            foreach ($scheduled_events as $se) {
                $scheduler_client = SchedulerClient::create($se['seminar_id']);
                $scheduler_client->updateEventForSeminar($se['seminar_id'], $se['resource_id'], $se['date_id'], $se['event_id']);

                unset($events[$se['event_id']]);

                $course = Course::find($se['seminar_id']);
                $date = new SingleDate($se['date_id']);
                echo sprintf(
                    _("Aktualisiere die Aufzeichnungsdaten für die Veranstaltung am %s für den Kurs %s\n "),
                    $date->getDatesExport(), $course->name
                );
            }
        }

        // the remaining events have no association in Stud.IP and need to be deleted
        foreach ($events as $event) {
            $scheduler_client = SchedulerClient::getInstance(1);
            $scheduler_client->deleteEvent($event->identifier);
        }

        return true;
    }
}
