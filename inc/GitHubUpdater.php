<?php
# https://github.com/erusev/parsedown
#
# Parsedown
# http://parsedown.org
#
# (c) Emanuil Rusev
# http://erusev.com
#
# For the full license information, please view the LICENSE file that was
# distributed with this source code.
#
#
/*
The MIT License (MIT)

Copyright (c) 2013 Emanuil Rusev, erusev.com

Permission is hereby granted, free of charge, to any person obtaining a copy of
this software and associated documentation files (the "Software"), to deal in
the Software without restriction, including without limitation the rights to
use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of
the Software, and to permit persons to whom the Software is furnished to do so,
subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS
FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR
COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER
IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN
CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
*/

class Parsedown
{
    #
    # Multiton (http://en.wikipedia.org/wiki/Multiton_pattern)
    #

    static function instance($name = 'default')
    {
        if (isset(self::$instances[$name]))
            return self::$instances[$name];

        $instance = new Parsedown();

        self::$instances[$name] = $instance;

        return $instance;
    }

    private static $instances = array();

    #
    # Fields
    #

    private $reference_map = array();
    private $escape_sequence_map = array();

    #
    # Public Methods
    #

    function parse($text)
    {
        # removes UTF-8 BOM and marker characters
        $text = preg_replace('{^\xEF\xBB\xBF|\x1A}', '', $text);

        # removes \r characters
        $text = str_replace("\r\n", "\n", $text);
        $text = str_replace("\r", "\n", $text);

        # replaces tabs with spaces
        $text = str_replace("\t", '    ', $text);

        # encodes escape sequences

        if (strpos($text, '\\') !== FALSE)
        {
            $escape_sequences = array('\\\\', '\`', '\*', '\_', '\{', '\}', '\[', '\]', '\(', '\)', '\>', '\#', '\+', '\-', '\.', '\!');

            foreach ($escape_sequences as $index => $escape_sequence)
            {
                if (strpos($text, $escape_sequence) !== FALSE)
                {
                    $code = "\x1A".'\\'.$index.';';

                    $text = str_replace($escape_sequence, $code, $text);

                    $this->escape_sequence_map[$code] = $escape_sequence;
                }
            }
        }

        # ~

        $text = preg_replace('/\n\s*\n/', "\n\n", $text);
        $text = trim($text, "\n");

        $lines = explode("\n", $text);

        $text = $this->parse_block_elements($lines);

        # decodes escape sequences

        foreach ($this->escape_sequence_map as $code => $escape_sequence)
        {
            $text = str_replace($code, $escape_sequence[1], $text);
        }

        $text = rtrim($text, "\n");

        return $text;
    }

    #
    # Private Methods
    #

    private function parse_block_elements(array $lines, $context = '')
    {
        $elements = array();

        $element = array(
            'type' => '',
       );

        foreach ($lines as $line)
        {
            #
            # fenced elements

            switch ($element['type'])
            {
                case 'fenced_code_block':

                    if (! isset($element['closed']))
                    {
                        if (preg_match('/^[ ]*'.$element['fence'][0].'{3,}[ ]*$/', $line))
                        {
                            $element['closed'] = true;
                        }
                        else
                        {
                            $element['text'] !== '' and $element['text'] .= "\n";

                            $element['text'] .= $line;
                        }

                        continue 2;
                    }

                    break;

                case 'markup':

                    if (! isset($element['closed']))
                    {
                        if (preg_match('{<'.$element['subtype'].'>$}', $line)) # opening tag
                        {
                            $element['depth']++;
                        }

                        if (preg_match('{</'.$element['subtype'].'>$}', $line)) # closing tag
                        {
                            $element['depth'] > 0
                                ? $element['depth']--
                                : $element['closed'] = true;
                        }

                        $element['text'] .= "\n".$line;

                        continue 2;
                    }

                    break;
            }

            # *

            if ($line === '')
            {
                $element['interrupted'] = true;

                continue;
            }

            #
            # composite elements

            switch ($element['type'])
            {
                case 'blockquote':

                    if (! isset($element['interrupted']))
                    {
                        $line = preg_replace('/^[ ]*>[ ]?/', '', $line);

                        $element['lines'] []= $line;

                        continue 2;
                    }

                    break;

                case 'li':

                    if (preg_match('/^([ ]{0,3})(\d+[.]|[*+-])[ ](.*)/', $line, $matches))
                    {
                        if ($element['indentation'] !== $matches[1])
                        {
                            $element['lines'] []= $line;
                        }
                        else
                        {
                            unset($element['last']);

                            $elements []= $element;

                            $element = array(
                                'type' => 'li',
                                'indentation' => $matches[1],
                                'last' => true,
                                'lines' => array(
                                    preg_replace('/^[ ]{0,4}/', '', $matches[3]),
                               ),
                           );
                        }

                        continue 2;
                    }

                    if (isset($element['interrupted']))
                    {
                        if ($line[0] === ' ')
                        {
                            $element['lines'] []= '';

                            $line = preg_replace('/^[ ]{0,4}/', '', $line);

                            $element['lines'] []= $line;

                            continue 2;
                        }
                    }
                    else
                    {
                        $line = preg_replace('/^[ ]{0,4}/', '', $line);

                        $element['lines'] []= $line;

                        continue 2;
                    }

                    break;
            }

            #
            # indentation sensitive types

            $deindented_line = $line;

            switch ($line[0])
            {
                case ' ':

                    # ~

                    $deindented_line = ltrim($line);

                    if ($deindented_line === '')
                    {
                        continue 2;
                    }

                    # code block

                    if (preg_match('/^[ ]{4}(.*)/', $line, $matches))
                    {
                        if ($element['type'] === 'code_block')
                        {
                            if (isset($element['interrupted']))
                            {
                                $element['text'] .= "\n";

                                unset ($element['interrupted']);
                            }

                            $element['text'] .= "\n".$matches[1];
                        }
                        else
                        {
                            $elements []= $element;

                            $element = array(
                                'type' => 'code_block',
                                'text' => $matches[1],
                           );
                        }

                        continue 2;
                    }

                    break;

                case '#':

                    # atx heading (#)

                    if (preg_match('/^(#{1,6})[ ]*(.+?)[ ]*#*$/', $line, $matches))
                    {
                        $elements []= $element;

                        $level = strlen($matches[1]);

                        $element = array(
                            'type' => 'h.',
                            'text' => $matches[2],
                            'level' => $level,
                       );

                        continue 2;
                    }

                    break;

                case '-':

                    # setext heading (---)

                    if ($line[0] === '-' and $element['type'] === 'p' and ! isset($element['interrupted']) and preg_match('/^[-]+[ ]*$/', $line))
                    {
                        $element['type'] = 'h.';
                        $element['level'] = 2;

                        continue 2;
                    }

                    break;

                case '=':

                    # setext heading (===)

                    if ($line[0] === '=' and $element['type'] === 'p' and ! isset($element['interrupted']) and preg_match('/^[=]+[ ]*$/', $line))
                    {
                        $element['type'] = 'h.';
                        $element['level'] = 1;

                        continue 2;
                    }

                    break;
            }

            #
            # indentation insensitive types

            switch ($deindented_line[0])
            {
                case '<':

                    # self-closing tag

                    if (preg_match('{^<.+?/>$}', $deindented_line))
                    {
                        $elements []= $element;

                        $element = array(
                            'type' => '',
                            'text' => $deindented_line,
                       );

                        continue 2;
                    }

                    # opening tag

                    if (preg_match('{^<(\w+)(?:[ ].*?)?>}', $deindented_line, $matches))
                    {
                        $elements []= $element;

                        $element = array(
                            'type' => 'markup',
                            'subtype' => strtolower($matches[1]),
                            'text' => $deindented_line,
                            'depth' => 0,
                       );

                        preg_match('{</'.$matches[1].'>\s*$}', $deindented_line) and $element['closed'] = true;

                        continue 2;
                    }

                    break;

                case '>':

                    # quote

                    if (preg_match('/^>[ ]?(.*)/', $deindented_line, $matches))
                    {
                        $elements []= $element;

                        $element = array(
                            'type' => 'blockquote',
                            'lines' => array(
                                $matches[1],
                           ),
                       );

                        continue 2;
                    }

                    break;

                case '[':

                    # reference

                    if (preg_match('/^\[(.+?)\]:[ ]*([^ ]+)/', $deindented_line, $matches))
                    {
                        $label = strtolower($matches[1]);

                        $this->reference_map[$label] = trim($matches[2], '<>');;

                        continue 2;
                    }

                    break;

                case '`':
                case '~':

                    # fenced code block

                    if (preg_match('/^([`]{3,}|[~]{3,})[ ]*(\S+)?[ ]*$/', $deindented_line, $matches))
                    {
                        $elements []= $element;

                        $element = array(
                            'type' => 'fenced_code_block',
                            'text' => '',
                            'fence' => $matches[1],
                       );

                        isset($matches[2]) and $element['language'] = $matches[2];

                        continue 2;
                    }

                    break;

                case '*':
                case '+':
                case '-':
                case '_':

                    # hr

                    if (preg_match('/^([-*_])([ ]{0,2}\1){2,}[ ]*$/', $deindented_line))
                    {
                        $elements []= $element;

                        $element = array(
                            'type' => 'hr',
                       );

                        continue 2;
                    }

                    # li

                    if (preg_match('/^([ ]*)[*+-][ ](.*)/', $line, $matches))
                    {
                        $elements []= $element;

                        $element = array(
                            'type' => 'li',
                            'ordered' => false,
                            'indentation' => $matches[1],
                            'last' => true,
                            'lines' => array(
                                preg_replace('/^[ ]{0,4}/', '', $matches[2]),
                           ),
                       );

                        continue 2;
                    }
            }

            # li

            if ($deindented_line[0] <= '9' and $deindented_line >= '0' and preg_match('/^([ ]*)\d+[.][ ](.*)/', $line, $matches))
            {
                $elements []= $element;

                $element = array(
                    'type' => 'li',
                    'ordered' => true,
                    'indentation' => $matches[1],
                    'last' => true,
                    'lines' => array(
                        preg_replace('/^[ ]{0,4}/', '', $matches[2]),
                   ),
               );

                continue;
            }

            # paragraph

            if ($element['type'] === 'p')
            {
                if (isset($element['interrupted']))
                {
                    $elements []= $element;

                    $element['text'] = $line;

                    unset($element['interrupted']);
                }
                else
                {
                    $element['text'] .= "\n".$line;
                }
            }
            else
            {
                $elements []= $element;

                $element = array(
                    'type' => 'p',
                    'text' => $line,
               );
            }
        }

        $elements []= $element;

        unset($elements[0]);

        #
        # ~
        #

        $markup = '';

        foreach ($elements as $element)
        {
            switch ($element['type'])
            {
                case 'p':

                    $text = $this->parse_span_elements($element['text']);

                    $text = preg_replace('/[ ]{2}\n/', '<br />'."\n", $text);

                    if ($context === 'li' and $markup === '')
                    {
                        if (isset($element['interrupted']))
                        {
                            $markup .= "\n".'<p>'.$text.'</p>'."\n";
                        }
                        else
                        {
                            $markup .= $text;
                        }
                    }
                    else
                    {
                        $markup .= '<p>'.$text.'</p>'."\n";
                    }

                    break;

                case 'blockquote':

                    $text = $this->parse_block_elements($element['lines']);

                    $markup .= '<blockquote>'."\n".$text.'</blockquote>'."\n";

                    break;

                case 'code_block':
                case 'fenced_code_block':

                    $text = htmlspecialchars($element['text'], ENT_NOQUOTES, 'UTF-8');

                    strpos($text, "\x1A\\") !== FALSE and $text = strtr($text, $this->escape_sequence_map);

                    $markup .= '<pre><code>'.$text.'</code></pre>'."\n";

                    break;

                case 'h.':

                    $text = $this->parse_span_elements($element['text']);

                    $markup .= '<h'.$element['level'].'>'.$text.'</h'.$element['level'].'>'."\n";

                    break;

                case 'hr':

                    $markup .= '<hr />'."\n";

                    break;

                case 'li':

                    if (isset($element['ordered'])) # first
                    {
                        $list_type = $element['ordered'] ? 'ol' : 'ul';

                        $markup .= '<'.$list_type.'>'."\n";
                    }

                    if (isset($element['interrupted']) and ! isset($element['last']))
                    {
                        $element['lines'] []= '';
                    }

                    $text = $this->parse_block_elements($element['lines'], 'li');

                    $markup .= '<li>'.$text.'</li>'."\n";

                    isset($element['last']) and $markup .= '</'.$list_type.'>'."\n";

                    break;

                default:

                    $markup .= $element['text']."\n";
            }
        }

        return $markup;
    }

    private function parse_span_elements($text)
    {
        $map = array();

        $index = 0;

        # code span

        if (strpos($text, '`') !== FALSE and preg_match_all('/`(.+?)`/', $text, $matches, PREG_SET_ORDER))
        {
            foreach ($matches as $matches)
            {
                $element_text = $matches[1];
                $element_text = htmlspecialchars($element_text, ENT_NOQUOTES, 'UTF-8');

                # decodes escape sequences

                $this->escape_sequence_map
                    and strpos($element_text, "\x1A") !== FALSE
                    and $element_text = strtr($element_text, $this->escape_sequence_map);

                # composes element

                $element = '<code>'.$element_text.'</code>';

                # encodes element

                $code = "\x1A".'$'.$index;

                $text = str_replace($matches[0], $code, $text);

                $map[$code] = $element;

                $index ++;
            }
        }

        # inline link or image

        if (strpos($text, '](') !== FALSE and preg_match_all('/(!?)(\[((?:[^\[\]]|(?2))*)\])\((.*?)\)/', $text, $matches, PREG_SET_ORDER)) # inline
        {
            foreach ($matches as $matches)
            {
                $url = $matches[4];

                strpos($url, '&') !== FALSE and $url = preg_replace('/&(?!#?\w+;)/', '&amp;', $url);

                if ($matches[1]) # image
                {
                    $element = '<img alt="'.$matches[3].'" src="'.$url.'">';
                }
                else
                {
                    $element_text = $this->parse_span_elements($matches[3]);

                    $element = '<a href="'.$url.'">'.$element_text.'</a>';
                }

                # ~

                $code = "\x1A".'$'.$index;

                $text = str_replace($matches[0], $code, $text);

                $map[$code] = $element;

                $index ++;
            }
        }

        # reference link or image

        if ($this->reference_map and strpos($text, '[') !== FALSE and preg_match_all('/(!?)\[(.+?)\](?:\n?[ ]?\[(.*?)\])?/ms', $text, $matches, PREG_SET_ORDER))
        {
            foreach ($matches as $matches)
            {
                $link_definition = isset($matches[3]) && $matches[3]
                    ? $matches[3]
                    : $matches[2]; # implicit

                $link_definition = strtolower($link_definition);

                if (isset($this->reference_map[$link_definition]))
                {
                    $url = $this->reference_map[$link_definition];

                    strpos($url, '&') !== FALSE and $url = preg_replace('/&(?!#?\w+;)/', '&amp;', $url);

                    if ($matches[1]) # image
                    {
                        $element = '<img alt="'.$matches[2].'" src="'.$url.'">';
                    }
                    else # anchor
                    {
                        $element_text = $this->parse_span_elements($matches[2]);

                        $element = '<a href="'.$url.'">'.$element_text.'</a>';
                    }

                    # ~

                    $code = "\x1A".'$'.$index;

                    $text = str_replace($matches[0], $code, $text);

                    $map[$code] = $element;

                    $index ++;
                }
            }
        }

        if (strpos($text, '://') !== FALSE)
        {
            switch (TRUE)
            {
                case preg_match_all('{<(https?:[/]{2}[^\s]+)>}i', $text, $matches, PREG_SET_ORDER):
                case preg_match_all('{\b(https?:[/]{2}[^\s]+)\b}i', $text, $matches, PREG_SET_ORDER):

                    foreach ($matches as $matches)
                    {
                        $url = $matches[1];

                        strpos($url, '&') !== FALSE and $url = preg_replace('/&(?!#?\w+;)/', '&amp;', $url);

                        $element = '<a href=":href">:text</a>';
                        $element = str_replace(':text', $url, $element);
                        $element = str_replace(':href', $url, $element);

                        # ~

                        $code = "\x1A".'$'.$index;

                        $text = str_replace($matches[0], $code, $text);

                        $map[$code] = $element;

                        $index ++;
                    }

                    break;
            }
        }

        # ~

        strpos($text, '&') !== FALSE and $text = preg_replace('/&(?!#?\w+;)/', '&amp;', $text);
        strpos($text, '<') !== FALSE and $text = preg_replace('/<(?!\/?\w.*?>)/', '&lt;', $text);

        # ~

        if (strpos($text, '~~') !== FALSE)
        {
            $text = preg_replace('/~~(?=\S)(.+?)(?<=\S)~~/s', '<del>$1</del>', $text);
        }

        if (strpos($text, '_') !== FALSE)
        {
            $text = preg_replace('/__(?=\S)([^_]+?)(?<=\S)__/s', '<strong>$1</strong>', $text, -1, $count);
            $count or $text = preg_replace('/__(?=\S)(.+?)(?<=\S)__(?!_)/s', '<strong>$1</strong>', $text);

            $text = preg_replace('/\b_(?=\S)(.+?)(?<=\S)_\b/s', '<em>$1</em>', $text);
        }

        if (strpos($text, '*') !== FALSE)
        {
            $text = preg_replace('/\*\*(?=\S)([^*]+?)(?<=\S)\*\*/s', '<strong>$1</strong>', $text, -1, $count);
            $count or $text = preg_replace('/\*\*(?=\S)(.+?)(?<=\S)\*\*(?!\*)/s', '<strong>$1</strong>', $text);

            $text = preg_replace('/\*(?=\S)([^*]+?)(?<=\S)\*/s', '<em>$1</em>', $text, -1, $count);
            $count or $text = preg_replace('/\*(?=\S)(.+?)(?<=\S)\*(?!\*)/s', '<em>$1</em>', $text);
        }

        $text = strtr($text, $map);

        return $text;
    }
}

class GitHubUpdater
{

    private $slug;

    private $pluginData;

    private $username;

    private $repo;

    private $pluginFile;

    private $githubAPIResult;

    private $accessToken;

    private $pluginActivated;

    /**
     * Class constructor.
     *
     * @param  string $pluginFile
     * @param  string $gitHubUsername
     * @param  string $gitHubProjectName
     * @param  string $accessToken
     * @return null
     */
    function __construct($pluginFile, $gitHubUsername, $gitHubProjectName, $accessToken = '')
    {
        add_filter("pre_set_site_transient_update_plugins", array($this, "setTransitent"));
        add_filter("plugins_api", array($this, "setPluginInfo"), 10, 3);
        add_filter("upgrader_pre_install", array($this, "preInstall"), 10, 3);
        add_filter("upgrader_post_install", array($this, "postInstall"), 10, 3);

        $this->pluginFile 	= $pluginFile;
        $this->username 	= $gitHubUsername;
        $this->repo 		= $gitHubProjectName;
        $this->accessToken 	= $accessToken;
    }

    /**
     * Get information regarding our plugin from WordPress
     *
     * @return null
     */
    private function initPluginData()
    {
		$this->slug = plugin_basename($this->pluginFile);

		$this->pluginData = get_plugin_data($this->pluginFile);
    }

    /**
     * Get information regarding our plugin from GitHub
     *
     * @return null
     */
    private function getRepoReleaseInfo()
    {
        if (! empty($this->githubAPIResult))
        {
    		return;
		}

		// Query the GitHub API
		$url = "https://api.github.com/repos/{$this->username}/{$this->repo}/releases";

		if (! empty($this->accessToken))
		{
		    $url = add_query_arg(array("access_token" => $this->accessToken), $url);
		}

		// Get the results
		$this->githubAPIResult = wp_remote_retrieve_body(wp_remote_get($url));

		if (! empty($this->githubAPIResult))
		{
		    $this->githubAPIResult = @json_decode($this->githubAPIResult);
		}

		// Use only the latest release
		if (is_array($this->githubAPIResult))
		{
		    $this->githubAPIResult = $this->githubAPIResult[0];
		}
    }

    /**
     * Push in plugin version information to get the update notification
     *
     * @param  object $transient
     * @return object
     */
    public function setTransitent($transient)
    {
        if (empty($transient->checked))
        {
    		return $transient;
		}

		// Get plugin & GitHub release information
		$this->initPluginData();
		$this->getRepoReleaseInfo();

		$doUpdate = version_compare($this->githubAPIResult->tag_name, $transient->checked[$this->slug]);

		if ($doUpdate && isset($this->githubAPIResult->zipball_url))
		{
			$package = $this->githubAPIResult->zipball_url;

			if (! empty($this->accessToken))
			{
			    $package = add_query_arg(array("access_token" => $this->accessToken), $package);
			}

			// Plugin object
			$obj = new stdClass();
			$obj->slug = $this->slug;
			$obj->new_version = $this->githubAPIResult->tag_name;
			$obj->url = $this->pluginData["PluginURI"];
			$obj->package = $package;

			$transient->response[$this->slug] = $obj;
		}

        return $transient;
    }

    /**
     * Push in plugin version information to display in the details lightbox
     *
     * @param  boolean $false
     * @param  string $action
     * @param  object $response
     * @return object
     */
    public function setPluginInfo($false, $action, $response)
    {
		$this->initPluginData();
		$this->getRepoReleaseInfo();

		if (empty($response->slug) || $response->slug != $this->slug)
		{
		    return $false;
		}

		// Add our plugin information
		$response->last_updated     = $this->githubAPIResult->published_at;
		$response->slug             = $this->slug;
		$response->plugin_name      = $this->pluginData["Name"];
		$response->name             = $this->pluginData["Name"];
		$response->version          = $this->githubAPIResult->tag_name;
		$response->author           = $this->pluginData["AuthorName"];
		$response->homepage         = $this->pluginData["PluginURI"];

		// This is our release download zip file
		$downloadLink = $this->githubAPIResult->zipball_url;

		if (!empty($this->accessToken))
		{
		    $downloadLink = add_query_arg(
		        array("access_token" => $this->accessToken),
		        $downloadLink
		   );
		}

		$response->download_link = $downloadLink;

		// Create tabs in the lightbox
		$response->sections = array(
			'Description' 	=> $this->pluginData["Description"],
			'changelog' 	=> class_exists("Parsedown")
				? Parsedown::instance()->parse($this->githubAPIResult->body)
				: $this->githubAPIResult->body
		);

		// Gets the required version of WP if available
		$matches = null;
		preg_match("/requires:\s([\d\.]+)/i", $this->githubAPIResult->body, $matches);
		if (! empty($matches)) {
		    if (is_array($matches)) {
		        if (count($matches) > 1) {
		            $response->requires = $matches[1];
		        }
		    }
		}

		// Gets the tested version of WP if available
		$matches = null;
		preg_match("/tested:\s([\d\.]+)/i", $this->githubAPIResult->body, $matches);
		if (! empty($matches)) {
		    if (is_array($matches)) {
		        if (count($matches) > 1) {
		            $response->tested = $matches[1];
		        }
		    }
		}

        return $response;
    }

    /**
     * Perform check before installation starts.
     *
     * @param  boolean $true
     * @param  array   $args
     * @return null
     */
    public function preInstall($true, $args)
    {
        // Get plugin information
		$this->initPluginData();

		// Check if the plugin was installed before...
    	$this->pluginActivated = is_plugin_active($this->slug);
    }

    /**
     * Perform additional actions to successfully install our plugin
     *
     * @param  boolean $true
     * @param  string $hook_extra
     * @param  object $result
     * @return object
     */
    public function postInstall($true, $hook_extra, $result)
    {
		global $wp_filesystem;

		// Since we are hosted in GitHub, our plugin folder would have a dirname of
		// reponame-tagname change it to our original one:
		$pluginFolder = WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . dirname($this->slug);
		$wp_filesystem->move($result['destination'], $pluginFolder);
		$result['destination'] = $pluginFolder;

		// Re-activate plugin if needed
		if ($this->pluginActivated)
		{
		    $activate = activate_plugin($this->slug);
		}

        return $result;
    }
}