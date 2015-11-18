<?php
echo __('
<p>Import par lot, fichier CSV, séparateur de cellule, tabulation (\\t), saut de ligne UNIX (\\n, LF).</p>
');
/*
echo '
<table>
  <tr>
    <th>URL</th>
    <th>Type</th>
    <th>Collection</th>
  </tr>
  <tr>
    <td>http://</td>
    <td>Letter</td>
    <td>From A to B</td>
  </tr>
  <tr>
    <td>http://</td>
    <td>Letter</td>
    <td>From B to A</td>
  </tr>
</table>
';
*/
echo get_view()->formFile('bookmeka_csv');
// hack to set form/@enctype, just after the field 
echo '<script type="text/javascript">document.getElementById("bookmeka_csv").form.enctype="multipart/form-data";</script>';
$options = get_db()->getTable('ItemType')->findPairsForSelectForm();
if (!empty($options)) {
  $options = array('' => __('Choisir un type d’item')) + $options;
  echo get_view()->formSelect('bookmeka_itemtype', null, null, $options);
}

$options = get_db()->getTable('Collection')->findPairsForSelectForm();
if (!empty($options)) {
  $options = array('' => __('Choisir une collection')) + $values;
  echo get_view()->formSelect('bookmeka_collection', null, array('' => __('Choisir une collection')), $options);
}
echo "<h3>".__('Formats de fichiers téléchargeables')."</h3>\n";
echo '<p class="explanation">'.__('
Bookmeka importe des textes dans plusieurs formats de fichier (office/odt, xml/tei).
Tous les formats d’import sont convertis dans un format pivot, xml/tei, 
à partir duquel sont générés les formats exportés.
Les formats importés autres que xml/tei ne sont pas conservés (considérés comme des fichiers de travail).
Par défaut, tous les formats générés sont visibles du public 
comme des fichiers attachés à un item Omeka.
Décocher un format le rend invisible du public pour tous les nouveaux items créés.
Pour que les anciens items suivent une modification de la politique, il faut regénérer tous les formats d’export (cf. ci-dessus).
Si le pivot xml/tei n’a pas été conservé, il n’est pas possible de regénérer les formats d’export à partir de cette 
interface, il faut réimporter les fichiers sources.
')."</p>\n";

echo '<fieldset class="bookmeka">'."\n";

echo '<label class="bookmeka">'."\n";
echo get_view()->formCheckbox('bookmeka_epub', true, array('checked'=>bookmeka_checked('bookmeka_epub')));
echo __('<b>epub</b>, livre électronique');
echo "\n</label>\n";

echo '<label class="bookmeka">'."\n";
echo get_view()->formCheckbox('bookmeka_html', true, array('checked'=>bookmeka_checked('bookmeka_html')));
echo __('<b>html</b>, monopage');
echo "\n</label>\n";

echo '<label class="bookmeka">'."\n";
echo get_view()->formCheckbox('bookmeka_iramuteq', true, array('checked'=>bookmeka_checked('bookmeka_iramuteq')));
echo __('<b>iramuteq</b>, textométrie');
echo "\n</label>\n";

echo '<label class="bookmeka">'."\n";
echo get_view()->formCheckbox('bookmeka_md', true, array('checked'=>bookmeka_checked('bookmeka_md')));
echo __('<b>md</b>, texte brut markdown');
echo "\n</label>\n";

echo '<label class="bookmeka">'."\n";
echo get_view()->formCheckbox('bookmeka_site', null, array('checked'=>bookmeka_checked('bookmeka_site')));
echo __('<b>site</b>, pages navigables dans Omeka');
echo "\n</label>\n";

echo '<label class="bookmeka">'."\n";
echo get_view()->formCheckbox('bookmeka_tei', true, array('checked'=>bookmeka_checked('bookmeka_tei')));
echo __('<b>tei</b>, format pivot');
echo "\n</label>\n";

echo "</fieldset>\n";

echo "<h3>".__('Options avancées')."</h3>\n";

echo '<p class="explanation">'.__('
Sur certains serveurs, il peut être nécessaire de définir un dossier temporaire où Bookmeka peut écrire et créer des dossiers (droits Apache).
')."</p>\n";
echo '<label class="bookmeka">'."\n";
echo get_view()->formText('bookmeka_tmpdir', get_option('bookmeka_tmpdir'));
echo __('Dossier de travail');
echo "\n</label>\n";

/* toc ?
echo '<div class="field todo">'."\n";
echo '<p class="explanation">'.__('
Les pages produites par Bookmeka peuvent s’intégrer dans les thèmes par défaut de Omeka (hooks PublicItemsShow et PublicHead).
Cependant, un projet éditorial peut demander l’écriture d’un thème spécifique.
En ce cas, les hooks par défaut peuvent être débranchés en cochant cette case,
le développeur d’un thème peut alors intégrer les contenus de Bookmeka à sa convenance 
avec des fonctions.
')."</p>\n";
echo '<label class="bookmeka">'."\n"
echo get_view()->formCheckbox('bookmeka_theme', true, array('checked'=>bookmeka_checked('bookmeka_theme', null)));
echo __('Thème compatible Bookmeka');
echo "\n</label>\n";
*/

echo '<div class="field todo">'."\n";
echo '<p class="explanation">'.__('
Pour les développeurs de sites, peut donner des informations utiles qui ne doivent pas être montrées au public.
')."</p>\n";
echo '<label class="bookmeka">'."\n";
echo get_view()->formCheckbox('bookmeka_debug', true, array('checked'=>bookmeka_checked('bookmeka_debug', null)));
echo __('Déboguage');
echo "\n</label>\n";

function bookmeka_checked($option, $default='checked') {
  $value = get_option($option);
  if (is_null($value)) return $default;
  if ($value) return "checked";
  else return false;
}

?>