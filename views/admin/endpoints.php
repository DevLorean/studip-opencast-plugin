<?
    use Studip\Button, Studip\LinkButton;

    $infobox_content = array(array(
        'kategorie' => $_('Hinweise:'),
        'eintrag'   => array(array(
            'icon' => 'icons/16/black/info.png',
            'text' => $_("Hier kann die Anbindung zum Opencast System verwaltet werden.")
        ))
    ));
    $infobox = array('picture' => 'infobox/administration.jpg', 'content' => $infobox_content);
?>
<?= $this->render_partial('messages') ?>
<script language="JavaScript">
OC.initAdmin();
</script>
<!--
<h3>Globale Opencast Einstellungen</h3>
<span>
  <?=$_("Tragen Sie hier den Pfad zum Opencast Runtime Information REST-Endpoint ein.")?>
</span> -->

<?= $this->render_partial("admin/_endpointoverview", array('endpoints' => $endpoints)) ?>
