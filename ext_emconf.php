<?php

/***************************************************************
 * Extension Manager/Repository config file for ext "cps_shortnr".
 *
 * Auto generated 06-02-2017 10:47
 *
 * Manual updates:
 * Only the data in the array - everything else is removed by next
 * writing. "version" and "dependencies" must not be touched!
 ***************************************************************/

$EM_CONF[$_EXTKEY] = array (
  'title' => 'Url Shortener',
  'description' => 'Builds links to pages and extension records with a tiny url',
  'category' => 'fe',
  'author' => 'Nicole Cordes',
  'author_email' => 'cordes@cps-it.de',
  'state' => 'beta',
  'uploadfolder' => 0,
  'createDirs' => '',
  'clearCacheOnLoad' => 0,
  'author_company' => '',
  'version' => '1.0.0',
  'constraints' => 
  array (
    'depends' => 
    array (
      'typo3' => '7.6.0-7.6.99',
    ),
    'conflicts' => 
    array (
    ),
    'suggests' => 
    array (
      'pagenotfoundhandling' => '',
    ),
  ),
  'autoload' => array(
      'psr-4' => array(
          'CPSIT\\CpsShortnr\\' => 'Classes/',
      ),
  ),
  '_md5_values_when_last_written' => 'a:6:{s:9:"ChangeLog";s:4:"83ce";s:21:"ext_conf_template.txt";s:4:"9d51";s:12:"ext_icon.gif";s:4:"5349";s:17:"ext_localconf.php";s:4:"7565";s:45:"Classes/Controller/PageNotFoundController.php";s:4:"9477";s:25:"Resources/cps_shortnr.txt";s:4:"e87d";}',
);

