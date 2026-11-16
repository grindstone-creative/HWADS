<?php
// Standalone regression: model Divi skipping registration on a non-singular route.
define('ABSPATH',__DIR__);$registered=false;$initializations=0;$order=[];
function shortcode_exists($tag) { return $GLOBALS['registered']; }
function et_builder_init_global_settings() { $GLOBALS['initializations']++; }
function et_builder_add_main_elements() { $GLOBALS['registered']=true; }
function apply_filters($tag,$body) { if (!$GLOBALS['registered']) { throw new Exception('Modules not initialized before content rendering'); }$GLOBALS['order'][]='content';return str_replace(['[et_pb_section]','[/et_pb_section]'],['<section>','</section>'],$body); }
function setup_postdata($p) {}
require __DIR__.'/../plugin/hpads/includes/divi.php';
HPADS_Divi::prepare_modules('Ordinary text');
if ($initializations!==0) { throw new Exception('Plain content initialized Divi'); }
$GLOBALS['post']=(object)['ID'=>10,'post_content'=>'Original'];$original=$GLOBALS['post'];
$output=HPADS_Divi::render('[et_pb_section]Published content[/et_pb_section]');
if ($output!=='<section>Published content</section>' || $initializations!==1 || $GLOBALS['post']!==$original) { throw new Exception('Route rendering failed'); }
HPADS_Divi::render('[et_pb_section]Second render[/et_pb_section]');
if ($initializations!==1) { throw new Exception('Modules initialized twice'); }
echo "PASS: plain content untouched, route initializes missing modules, rendered output, post context restored, repeated render safe\n";
