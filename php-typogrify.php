<?php

/*===============================================================
 * php-typogrify
 ================================================================
 * Prettifies your web typography by preventing ugly quotes and 'widows' 
 * and providing CSS hooks to style some special cases.
 * It's a port of the original Python code by Christian Metts.
 *
 *      Announcement:
 *      <http://www2.jeffcroft.com/sidenotes/2007/may/29/typogrify-easily-produce-web-typography-doesnt-suc/>
 *
 *      Example Page:
 *      <http://static.mintchaos.com/projects/typogrify/>
 *
 *      Project Page:
 *      <http://code.google.com/p/typogrify/>
 *
 *      PHP SmartyPants
 *      <http://www.michelf.com/projects/php-smartypants/>
 *
 * ==============================================================
 *
 * Copyright (c) 2007, Hamish Macpherson
 * 
 * All rights reserved.
 * 
 * Redistribution and use in source and binary forms, with or without modification, are permitted provided that the following conditions are met:
 * 
 *     * Redistributions of source code must retain the above copyright notice, this list of conditions and the following disclaimer.
 *     * Redistributions in binary form must reproduce the above copyright notice, this list of conditions and the following disclaimer in the documentation and/or other materials provided with the distribution.
 *     * Neither the name of the php-typogrify nor the names of its contributors may be used to endorse or promote products derived from this software without specific prior written permission.
 * 
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR
 * CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL,
 * EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO,
 * PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR
 * PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF
 * LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING
 * NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 * 
 *==============================================================*/

// WordPress Plugin Info

/*
Plugin Name: wp-typogrify
Plugin URI: http://blog.hamstu.com/
Description: Prettify your web typography by preventing ugly quotes and widows and providing CSS hooks to style some special cases. Based on the original Python code by Christian Metts (http://code.google.com/p/typogrify/)
Version: 1.0
Author: Hamish Macpherson
Author URI: http://www.hamstu.com/
*/

// We need some smartypants :)
//require("smartypants.php");
//require_once(dirname(__FILE__) . '/smartypants.php'); 
// Removed by TG to keep typogrify.module from conflicting with marksmarty.module.
// (Now we can load smartypants.php conditionally, depending on whether marksmarty.module exists)

/**
 * amp
 * 
 * Wraps apersands in html with ``<span class="amp">`` so they can be
 * styled with CSS. Apersands are also normalized to ``&amp;``. Requires 
 * ampersands to have whitespace or an ``&nbsp;`` on both sides.
 * 
 * It won't mess up & that are already wrapped, in entities or URLs
 */
function amp( $text )
{
    $amp_finder = "/(\s|&nbsp;)(&|&amp;|&\#38;)(\s|&nbsp;)/";
    return preg_replace($amp_finder, '\\1<span class="amp">&amp;</span>\\3', $text);
}

/**
 * This is necessary to keep dotted cap strings to pick up extra spaces
 * used in preg_replace_callback later on
 */
function _cap_wrapper( $matchobj )
{
    if ( !empty($matchobj[2]) )
    {
        return sprintf('<span class="caps">%s</span>', $matchobj[2]);
    }
    else 
    {
        $mthree = $matchobj[3];
        if ( ($mthree{strlen($mthree)-1}) == " " )
        {
            $caps = substr($mthree, 0, -1);
            $tail = ' ';
        }
        else
        {
            $caps = $mthree;
            $tail = '';
        }            
        return sprintf('<span class="caps">%s</span>%s', $caps, $tail);
    }
}

/**
 * caps
 *
 * Wraps multiple capital letters in ``<span class="caps">`` 
 * so they can be styled with CSS. 
 * 
 * Uses the smartypants tokenizer to not screw with HTML or with tags it shouldn't.
 */
function caps( $text )
{
    // Tokenize; see smartypants.php
    $tokens = _TokenizeHTML($text);    
    $result = array();
    $in_skipped_tag = false;
    
    $cap_finder = "/(
            (\b[A-Z\d]*        # Group 2: Any amount of caps and digits
            [A-Z]\d*[A-Z]      # A cap string much at least include two caps (but they can have digits between them)
            [A-Z\d]*\b)        # Any amount of caps and digits
            | (\b[A-Z]+\.\s?   # OR: Group 3: Some caps, followed by a '.' and an optional space
            (?:[A-Z]+\.\s?)+)  # Followed by the same thing at least once more
            (?:\s|\b|$))/x";
    
    $tags_to_skip_regex = "/<(\/)?(?:pre|code|kbd|script|math)[^>]*>/i";
    
    foreach ($tokens as $token)
    {
        if ( $token[0] == "tag" )
        {
            // Don't mess with tags.
            $result[] = $token[1];
            $close_match = preg_match($tags_to_skip_regex, $token[1]);            
            if ( $close_match )
            {
                $in_skipped_tag = true;
            }
            else
            {
                $in_skipped_tag = false;
            }
        }
        else
        {
            if ( $in_skipped_tag )
            {
                $result[] = $token[1];
            }
            else
            {
                $result[] = preg_replace_callback($cap_finder, _cap_wrapper, $token[1]);
            }
        }
    }        
    return join("", $result);    
}

function _quote_wrapper( $matchobj )
{
    if ( !empty($matchobj[7]) )
    {
        $classname = "dquo";
        $quote = $matchobj[7];
    }
    else
    {
        $classname = "quo";
        $quote = $matchobj[8];
    }
    return sprintf('%s<span class="%s">%s</span>', $matchobj[1], $classname, $quote);
}

/**
 * initial_quotes
 *
 * Wraps initial quotes in ``class="dquo"`` for double quotes or  
 * ``class="quo"`` for single quotes. Works in these block tags ``(h1-h6, p, li)``
 * and also accounts for potential opening inline elements ``a, em, strong, span, b, i``
 */
function initial_quotes( $text )
{
    $quote_finder = "/((<(p|h[1-6]|li)[^>]*>|^)                     # start with an opening p, h1-6, li or the start of the string
                    \s*                                             # optional white space! 
                    (<(a|em|span|strong|i|b)[^>]*>\s*)*)            # optional opening inline tags, with more optional white space for each.
                    ((\"|&ldquo;|&\#8220;)|('|&lsquo;|&\#8216;))    # Find me a quote! (only need to find the left quotes and the primes)
                                                                    # double quotes are in group 7, singles in group 8
                    /x";
                    
    return preg_replace_callback($quote_finder, _quote_wrapper, $text);
}

/**
 * widont
 * 
 * Replaces the space between the last two words in a string with ``&nbsp;``
 * Works in these block tags ``(h1-h6, p, li)`` and also accounts for 
 * potential closing inline elements ``a, em, strong, span, b, i``
 * 
 * Empty HTMLs shouldn't error
 */
function widont( $text )
{
    $widont_finder = "/(\s+)                                 # the space to replace
                    ([^<>\s]+                               # must be flollowed by non-tag non-space characters
                    \s*                                     # optional white space! 
                    (<\/(a|em|span|strong|i|b)[^>]*>\s*)*    # optional closing inline tags with optional white space after each
                    (<\/(p|h[1-6]|li)|$))                    # end with a closing p, h1-6, li or the end of the string
                    /x";
                    
    return preg_replace($widont_finder, '&nbsp;\\2', $text);
}

/**
 * typogrify
 * 
 * The super typography filter.   
 * Applies the following filters: widont, smartypants, caps, amp, initial_quotes
 */
function typogrify( $text )
{
    $text = amp( $text );
    $text = widont( $text );
    $text = SmartyPants( $text );
    $text = caps( $text );
    $text = initial_quotes( $text );
    
    return $text;
}

// Wordpress Plugin Hooks

if ( isset($wp_version) ) 
{
    // Remove default Texturize filter that would conflict with php-typogrify.
    // Pulled this bit from smartypants.php

    remove_filter('category_description', 'wptexturize');
    remove_filter('list_cats', 'wptexturize');
    remove_filter('comment_author', 'wptexturize');
    remove_filter('comment_text', 'wptexturize');
    remove_filter('single_post_title', 'wptexturize');
    remove_filter('the_title', 'wptexturize');
    remove_filter('the_content', 'wptexturize');
    remove_filter('the_excerpt', 'wptexturize');

    // Add php-typogrify filter with priority 10 (same as Texturize).
    add_filter('category_description', 'typogrify', 10);
    add_filter('list_cats', 'typogrify', 10);
    add_filter('comment_author', 'typogrify', 10);
    add_filter('comment_text', 'typogrify', 10);
    add_filter('single_post_title', 'typogrify', 10);
    add_filter('the_title', 'typogrify', 10);
    add_filter('the_content', 'typogrify', 10);
    add_filter('the_excerpt', 'typogrify', 10);
    
    // Strip the potential HTML in the header
    add_filter('wp_title', 'strip_tags');
}

?>