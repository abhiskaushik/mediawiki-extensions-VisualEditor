<?php
$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__  . '/../../..';
}
require_once $IP . '/maintenance/Maintenance.php';

/**
 * A generator for creating a static HTML loading sequence
 * for VisualEditor.
 *
 * Example usage:
 *
 *     # Update our static files
 *     $ php maintenance/makeStaticLoader.php --target demo --write-file demos/ve/index.php
 *     $ php maintenance/makeStaticLoader.php --target test --write-file modules/ve/test/index.php
 *
 * @author Timo Tijhof, 2013
 */
class MakeStaticLoader extends Maintenance {

	public function __construct() {
		parent::__construct();

		$this->addOption(
			'target',
			'Which target to use ("demo" or "test"). Default: demo',
			false,
			true
		);
		$this->addOption(
			'indent',
			'Indentation prefix to use (number of tabs or a string)',
			false,
			true
		);
		$this->addOption(
			've-path',
			'Override path to "VisualEditor/modules" (no trailing slash). Default by --target',
			false,
			true
		);
		$this->addOption(
			'write-file',
			'Automatically replace the "Generated by" sections in this file. Default: false',
			false,
			true
		);
		$this->addOption(
			'fixdir',
			'Embed the absolute path in require() statements. Defaults to relative path. '
				. '(use this if you evaluate the resulting script in php-STDIN instead of from a file)',
			false,
			true
		);
		$this->addOption( 'section', 'head, body or both', false, true );
	}

	public function execute() {
		global $wgResourceModules, $wgHtml5, $wgWellFormedXml;

		$wgHtml5 = true;
		$wgWellFormedXml = false;

		$section = $this->getOption( 'section', 'both' );
		$target = $this->getOption( 'target', 'demo' );
		$indent = $this->getOption( 'indent', 2 );
		$writeFile = $this->getOption( 'write-file', false );

		if ( is_numeric( $indent ) ) {
			$indent = str_repeat( "\t", $indent );
		}

		// Path to /modules/
		$vePath = $this->getOption( 've-path',
			$target === 'demo' ?
			// From /demos/ve/index.php
			'../../modules' :
			// From /modules/ve/test/index.html
			'../..'
		);

		$wgResourceModules['Dependencies'] = array(
			'scripts' => array(
				'jquery/jquery.js',
				'jquery/jquery.client.js',
				'oojs/oojs.js',
				'rangy/rangy-core-1.3.js',
				'rangy/rangy-position-1.3.js',
				'unicodejs/unicodejs.js',
				'unicodejs/unicodejs.textstring.js',
				'unicodejs/unicodejs.graphemebreakproperties.js',
				'unicodejs/unicodejs.graphemebreak.js',
				'unicodejs/unicodejs.wordbreakproperties.js',
				'unicodejs/unicodejs.wordbreak.js',
			),
		);

		// If we're running this script from STDIN,
		// hardcode the full path
		$i18nScript = $this->getOption( 'fixdir' ) ?
			dirname( __DIR__ ) . '/VisualEditor.i18n.php' :
			$vePath . '/../VisualEditor.i18n.php';

		// Customized version to init standalone instead of mediawiki platform.
		$wgResourceModules['ext.visualEditor.base#standalone-init'] = array(
			'styles' => array(
				've/init/sa/styles/ve.init.sa.css',
			),
			'headAdd' => '<script>
	if (
		document.createElementNS &&
		document.createElementNS( \'http://www.w3.org/2000/svg\', \'svg\' ).createSVGRect
	) {
		document.write(
			\'<link rel="stylesheet" \' +
				\'href="' . $vePath . '/oojs-ui/styles/OO.ui.Icons-vector.css">\' +
			\'<link rel="stylesheet" \' +
				\'href="' . $vePath . '/ve/ui/styles/ve.ui.Icons-vector.css">\'
		);
	} else {
		document.write(
			\'<link rel="stylesheet" \' +
				\'href="' . $vePath . '/oojs-ui/styles/OO.ui.Icons-raster.css">\' +
			\'<link rel="stylesheet" \' +
				\'href="' . $vePath . '/ve/ui/styles/ve.ui.Icons-raster.css">\'
		);
	}
</script>',
			'bodyAdd' => '<script>
	<?php
		require ' . var_export( $i18nScript, true ) . ';
		echo \'ve.init.platform.addMessages( \' . json_encode( $messages[\'en\'] ) . " );\n";
	?>
	ve.init.platform.setModulesUrl( \'' . $vePath . '\' );
</script>'
		) + $wgResourceModules['ext.visualEditor.base'];
		$baseScripts = &$wgResourceModules['ext.visualEditor.base#standalone-init']['scripts'];
		$baseScripts = array_filter( $baseScripts, function ( $script ) {
			return strpos( $script, 've/init/mw/ve.init.mw' ) === false;
		} );
		$baseScripts = array_merge( $baseScripts, array(
			've/init/sa/ve.init.sa.js',
			've/init/sa/ve.init.sa.Platform.js',
			've/init/sa/ve.init.sa.Target.js',
		) );

		$self = isset( $_SERVER['PHP_SELF'] ) ? $_SERVER['PHP_SELF'] : ( lcfirst( __CLASS__ ) . '.php' );

		$head = $body = '';

		$modules = array(
			'Dependencies',
			'oojs-ui',
			'ext.visualEditor.base#standalone-init',
			'ext.visualEditor.core',
			'jquery.uls.grid',
			'jquery.uls.data',
			'jquery.uls.compact',
			'jquery.uls',
			'ext.visualEditor.language',
		);

		foreach ( $modules as $module ) {
			if ( !isset( $wgResourceModules[$module] ) ) {
				echo "\nError: Module $module\n not found!\n";
				exit( 1 );
			}
			$registry = $wgResourceModules[$module];

			$headAdd = $bodyAdd = '';

			if ( isset( $registry['styles'] ) && $target !== 'test' ){
				foreach ( (array)$registry['styles'] as $path ) {
					if ( strpos( $path, 've-mw/' ) === 0 ) {
						continue;
					}
					$headAdd .= $indent . Html::element( 'link', array(
						'rel' => 'stylesheet',
						'href' => "$vePath/$path",
					) ) . "\n";
				}
			}
			if ( isset( $registry['scripts'] ) ) {
				foreach ( (array)$registry['scripts'] as $path ) {
					if ( strpos( $path, 've-mw/' ) === 0 ) {
						continue;
					}
					$bodyAdd .= $indent . Html::element( 'script', array( 'src' => "$vePath/$path" ) ) . "\n";
				}
			}
			if ( isset( $registry['debugScripts'] ) ) {
				foreach ( (array)$registry['debugScripts'] as $path ) {
					if ( strpos( $path, 've-mw/' ) === 0 ) {
						continue;
					}
					$bodyAdd .= $indent . Html::element( 'script', array( 'src' => "$vePath/$path" ) ) . "\n";
				}
			}
			if ( isset( $registry['headAdd'] ) ) {
				$headAdd .= $indent . implode( "\n$indent", explode( "\n", $registry['headAdd'] ) ) . "\n";
			}
			if ( isset( $registry['bodyAdd'] ) ) {
				$bodyAdd .= $indent . implode( "\n$indent", explode( "\n", $registry['bodyAdd'] ) ) . "\n";
			}

			if ( $headAdd ) {
				$head .= "$indent<!-- $module -->\n$headAdd";
			}
			if ( $bodyAdd ) {
				$body .= "$indent<!-- $module -->\n$bodyAdd";
			}
		}

		$head = rtrim( $head );
		$body = rtrim( $body );

		// Output

		if ( $writeFile ) {
			$contents = is_readable( $writeFile ) ? file_get_contents( $writeFile ) : false;
			if ( !$contents ) {
				echo "\nError: Write file not readable or empty!\n";
				exit( 1 );
			}
			$lines = explode( "\n", $contents . "\n" );
			$inHead = $inBody = $inGenerated = false;
			foreach ( $lines as $i => &$line ) {
				$text = trim( $line );
				if ( $text === '<head>' ) {
					$inHead = true;
					$inBody = false;
					$inGenerated = false;
				} elseif ( $text === '<body>' ) {
					$inHead = false;
					$inBody = true;
					$inGenerated = false;
				} elseif ( strpos( $text, '<!-- Generated by' ) === 0 ) {
					// Only set $inGenerated if we're in a generated section
					// that we want to replace (--section=body, don't replace head).
					if ( $inHead ) {
						if ( $section === 'both' || $section === 'head' ) {
							$inGenerated = true;
							if ( !$head ) {
								$line = '';
							} else {
								$line = "$indent<!-- Generated by $self -->\n$head";
							}
						}
					} elseif ( $inBody ) {
						if ( $section === 'both' || $section === 'body' ) {
							$inGenerated = true;
							if ( !$body ) {
								$line = '';
							} else {
								$line = "$indent<!-- Generated by $self -->\n$body";
							}
						}
					}

				} elseif ( $text === '' ) {
					$inGenerated = false;
				} else {
					// Strip the lines directly connected to the "<!-- Generated by"
					if ( $inGenerated ) {
						unset( $lines[$i] );
					}
				}
			}
			if ( !file_put_contents( $writeFile, trim( implode( "\n", $lines ) ) . "\n" ) ) {
				echo "\nError: Write to file failed!\n";
				exit( 1 );
			}
			echo "Done!\n";

		} else {

			if ( $head ) {
				if ( $section === 'both' ) {
					echo "<head>\n\n$indent<!-- Generated by $self -->\n$head\n\n</head>";
				} elseif ( $section === 'head' ) {
					echo $head;
				}
			}
			if ( $body ) {
				if ( $section === 'both' ) {
					echo "<body>\n\n$indent<!-- Generated by $self -->\n$body\n\n</body>\n";
				} elseif ( $section === 'body' ) {
					echo $body;
				}
			}
		}

	}
}

$maintClass = 'MakeStaticLoader';
require_once RUN_MAINTENANCE_IF_MAIN;
