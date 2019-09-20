<? use Studip\Button, Studip\LinkButton; ?>

<form
    action="<?= PluginEngine::getLink('opencast/course/edit/' . $course_id) ?>"
    method=post id="select-series" class="default"
    data-unconnected="<?= (empty($connectedSeries) ? 1 : 'false');?>"
>
    <fieldset>
        <legend>
            <?= $_('Serie mit Veranstaltung verknüpfen') ?>
        </legend>

        <? if (!empty($all_series)) : ?>
            <label>
                <select name="series"
                    id="series-select"
                    data-placeholder="<?=$_('Wählen Sie eine Series aus.')?>"
                    style="max-width: 500px"
                >

                <? foreach ($configs as $id => $config): ?>
                <optgroup label="<?= $_(sprintf('%s. Opencast-System', $id)) ?>">
                    <? foreach ($all_series[$id] as $serie) : ?>
                        <?// if (isset($serie['identifier'])) : ?>
                            <option value='{"config_id":"<?= $id ?>", "series_id":"<?= $serie->id ?>"}'
                                    class="nested-item">
                                <?= $serie->dcTitle ?>
                            </option>
                        <?//endif;?>
                    <?endforeach;?>
                </optgroup>
                <? endforeach ?>
                </select>
            </label>
        <? endif;?>
    </fieldset>



    <footer data-dialog-button>
        <?= Button::createAccept($_('Übernehmen'), array('title' => $_("Änderungen übernehmen"))); ?>
        <?= LinkButton::createCancel($_('Abbrechen'), PluginEngine::getLink('opencast/course/index')); ?>
    </footer>
</form>

<script type="text/javascript">
    jQuery("#series-select").select2({
        disable_search_threshold: 2,
        max_selected_options: 1,
        no_results_text: "Oops, nothing found!",
        width: "500px"
    });
</script>
