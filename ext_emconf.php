<?php

/***************************************************************
 * Extension Manager/Repository config file for ext "cps_shortnr".
 *
 * Auto generated 31-03-2017 07:53
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
  'version' => '2.0.0',
  'constraints' => 
  array (
    'depends' => 
    array (
      'typo3' => '7.6.0-8.7.99',
    ),
    'conflicts' => 
    array (
    ),
    'suggests' => 
    array (
      'pagenotfoundhandling' => '',
    ),
  ),
  '_md5_values_when_last_written' => 'a:15:{s:9:"ChangeLog";s:4:"a55d";s:9:"Readme.md";s:4:"2544";s:13:"composer.json";s:4:"af18";s:21:"ext_conf_template.txt";s:4:"6723";s:12:"ext_icon.gif";s:4:"5349";s:17:"ext_localconf.php";s:4:"53d8";s:45:"Classes/Controller/PageNotFoundController.php";s:4:"59aa";s:29:"Classes/Shortlink/Decoder.php";s:4:"7d55";s:29:"Classes/Shortlink/Encoder.php";s:4:"cb32";s:31:"Classes/Shortlink/Shortlink.php";s:4:"8ba2";s:25:"Resources/cps_shortnr.txt";s:4:"8624";s:44:"Tests/Functional/AbstractShortnrTestCase.php";s:4:"f279";s:58:"Tests/Functional/Controller/PageNotFoundControllerTest.php";s:4:"ca5f";s:55:"Tests/Functional/Fixtures/tx_news_domain_model_news.xml";s:4:"971b";s:44:"Tests/Functional/Shortlink/ShortlinkTest.php";s:4:"4312";}',
);

