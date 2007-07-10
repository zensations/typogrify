<?php

// We rely on some functions from SmartyPants. If it hasn't been loaded already, we'll load it now.
if (! (function_exists('SmartyPants'))) {
  require_once(dirname(__FILE__) . '/smartypants.php');
}

// Also, we need some regex code from SmartyPants
$sp_tags_to_skip = '<(/?)(?:pre|code|kbd|script|math)[\s>]';

// See http://www.unicode.org/charts/PDF/UFB00.pdf
$ligature_map = array(
  "ffi" => "&#xfb03;",
  "ffl" => "&#xfb04;",
  "ff"  => "&#xfb00;",
  "fi"  => "&#xfb01;",
  "fl"  => "&#xfb02;",
  "st"  => "&#xfb06;",
  "ft"  => "&#xfb05;",
  "ss"  => "&szlig;"
);

// See http:#www.unicode.org/charts/PDF/U2000.pdf
$punctuation_map = array(
  "..."   => "&#x2026;",
  ".."    => "&#x2025;",
  ". . ." => "&#x2026;",
  "---"   => "&mdash;",
  "--"    => "&ndash;",
);

// See http:#www.unicode.org/charts/PDF/U2190.pdf
$arrow_map = array(
  "->>" => "&#x21a0;",
  "<<-" => "&#x219e;",
  "->|" => "&#x21e5;",
  "|<-" => "&#x21e4;",
  "<->" => "&#x2194;",
  "->"  => "&#x2192;",
  "<-"  => "&#x2190;",
  "<=>" => "&#x21d4;",
  "=>"  => "&#x21d2;",
  "<="  => "&#x21d0;",
);

// Declare a global array of ascii to unicode mappings
global $unicode_map;

// put some mappings into the ascii to unicode mappings
$unicode_map = array_merge($ligature_map, $arrow_map, $punctuation_map);

function convert_characters($text, $characters_to_convert) {

  // Paramaters:
  // $text                    text to be parsed
  // $characters_to_convert   array of ascii characters to convert

  if (($characters_to_convert == NULL) || (count($characters_to_convert) < 1)) {
    // do nothing
    return $text;
  }

  // get ascii to unicode mappings
  global $unicode_map;
  
  foreach ($characters_to_convert as $ascii_string) {
    $unicode_strings[] = $unicode_map[$ascii_string];
  }
  
  $tokens = _TokenizeHTML($text);
  $result = '';
  $in_pre = 0;  // Keep track of when we're inside <pre> or <code> tags
  foreach ($tokens as $cur_token) {
    if ($cur_token[0] == "tag") {
      // Don't mess with text inside tags, <pre> blocks, or <code> blocks
      $result .= $cur_token[1];
      // Get the tags to skip regex from SmartyPants
      global $sp_tags_to_skip;
      if (preg_match("@$sp_tags_to_skip@", $cur_token[1], $matches)) {
        $in_pre = isset($matches[1]) && $matches[1] == '/' ? 0 : 1;
      }
    } else {
      $t = $cur_token[1];
      if ($in_pre == 0) {
        $t = ProcessEscapes($t);
        $t = str_replace($characters_to_convert, $unicode_strings, $t);
      }
      $result .= $t;
    }
  }
  return $result;
}

?>