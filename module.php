<?php

namespace vendor\WebtreesModules\OpenStreetMapModule;

use Composer\Autoload\ClassLoader;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Functions\Functions;
use Fisharebest\Webtrees\Filter;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Module\AbstractModule;
use Fisharebest\Webtrees\Module\ModuleTabInterface;

class OpenStreetMapModule extends AbstractModule implements ModuleTabInterface {

	var $directory;

	public function __construct()
	{
		parent::__construct('OpenStreetMapModule');
		$this->directory = WT_MODULES_DIR . $this->getName();
		$this->action = Filter::get('mod_action');
		// register the namespaces
		$loader = new ClassLoader();
		$loader->addPsr4('vendor\\WebtreesModules\\OpenStreetMapModule\\', $this->directory);
		$loader->register();
	}

	// Extend AbstractModule. Unique internal name for this module. Must match the directory name
	public function getName() {
		return "osm4webtrees";
	}

	// Extend AbstractModule. This title should be normalized when this module will be added officially
	public function getTitle() {
		return /* I18N: Name of a module */ I18N::translate('OpenStreetMap');
	}

	// Extend AbstractModule
	public function getDescription() {
		return /* I18N: Description of the “OSM” module */ I18N::translate('Show the location of places and events using OpenStreetMap (OSM)');
	}

	// Extend AbstractModule
	public function defaultAccessLevel() {
		# Auth::PRIV_PRIVATE actually means public.
		# Auth::PRIV_NONE - no acces to anybody.
		return Auth::PRIV_PRIVATE;
	}

	// Implement ModuleTabInterface
	public function defaultTabOrder() {
		return 81;
	}

	// Implement ModuleTabInterface
	public function getTabContent() {
/*		global $controller;*/
		$this->individualMap();
	}

	// Implement ModuleTabInterface
	public function hasTabContent() {
/*		global $SEARCH_SPIDER;*/

/*		return !$SEARCH_SPIDER;*/
		return true;
	}

	// Implement ModuleTabInterface
	public function isGrayedOut() {
		return false;
	}

	// Implement ModuleTabInterface
	public function canLoadAjax() {
/*		return true;*/
		return !Auth::isSearchEngine(); // Search engines cannot use AJAX
	}

	// Implement ModuleTabInterface
	public function getPreLoadContent() {
	}


	// Extend AbstractModule
	// Here, we define the actions available for the module
	public function modAction($mod_action) {
/*		switch($mod_action){
		case 'pedigree_map':
			$this->pedigreeMap();
		}*/

	}

/*	private function pedigreeMap() {
		global $controller;
		$controller = new WT_Controller_Pedigree();

		$this->includes($controller);
		$this->drawMap();
	}*/

	private function individualMap() {
		global $controller;

		$this->includes($controller);


		## This still needs some work. We'll probably want to copy this directly
		##   from googlemaps
		list($events, $popup, $geodata) = $this->getEvents();

		// If no places, display message and quit
		if (!$geodata) {
			echo "No map data for this person." . "\n";
			return;
		}

		$this->drawMap($events, $popup);

	}

	private function getEvents() {
		global $controller;

		$events = array(); # Array of indivuals/events
		$geodata = false; # Boolean indicating if we have any geo-tagged data

		$thisPerson = $controller->record;

		### Get all people that we want events for ###

		### This person self ###
//		$Xref=$thisPerson->getXref();
		$people[$thisPerson->getXref()] = $thisPerson;
//		array_push($people, $thisPerson); # Self

		### Parents and Sibblings ###
		foreach($thisPerson->getChildFamilies() as $family) {
			# Parents
			foreach($family->getSpouses() as $parent) {
				$people[$parent->getXref()] = $parent;
//				array_push($people, $parent);
			}

			# Siblings
			foreach($family->getChildren() as $sibling) {
				if ( $sibling !== $thisPerson) {
					$xref = $sibling->getXref();
					$people[$xref] = $sibling;
//					array_push($people, $child);
				}
			}
		}

		### Spouse and own Children ###
		foreach($thisPerson->getSpouseFamilies() as $family) {
			# Spouse
			foreach($family->getSpouses() as $spouse) {
				if ( $spouse !== $thisPerson) {
					$xref = $spouse->getXref();
					$people[$xref] = $spouse;
//					array_push($people, $spouse);
				}
			}

			# Children
			foreach($family->getChildren() as $child) {
				if ( $child !== $thisPerson) {
					$xref = $child->getXref();
					$people[$xref] = $child;
//				array_push($people, $child);
				}
			}
		}

		# Map each person to their facts
		// Basis info over persoon vast leggen
		foreach($people as $xref => $person) {
			$popup[$xref]=array(
				'CloseRelationshipName'=>Functions::getCloseRelationshipName($thisPerson,$person), 
				'HtmlUrl'=>$person->getHtmlUrl(), 
				'FullName'=>$person->getFullName(), 
				'LifeSpan'=>$person->getLifeSpan() );

			//events uitlezen
			$facts=$person->getFacts();
			foreach ($person->getSpouseFamilies() as $family){
				foreach ($family->getFacts() as $fact){
					$facts[]=$fact;
				}
			}
			
			Functions::sortFacts($facts);	
					
			$events[$xref] = array();
			foreach($facts as $fact) {
				$placefact = new \FactPlace($fact); //zie classes
				array_push($events[$xref], $placefact);
				if ($placefact->knownLatLon()) $geodata = true;
			}

				

			// sort facts by date => is done earlier
//			usort($events[$xref], array('FactPlace','CompareDate'));
		}

		return array($events, $popup, $geodata);
	}

	private function includes($controller) {
		// Leaflet JS
		echo '<script src="', WT_STATIC_URL, WT_MODULES_DIR, $this->getName().'/js/leaflet/leaflet.js"></script>';
		// Leaflet CSS
		echo '<link type="text/css" href="', WT_STATIC_URL, WT_MODULES_DIR, $this->getName().'/css/leaflet.css" rel="stylesheet">';
		echo '<link type="text/css" href="', WT_STATIC_URL, WT_MODULES_DIR, $this->getName().'/css/osm-module.css" rel="stylesheet">';

		// Leaflet markercluster
		echo '<link type="text/css" href="', WT_STATIC_URL, WT_MODULES_DIR, $this->getName().'/css/MarkerCluster.Default.css" rel="stylesheet">';
		echo '<link type="text/css" href="', WT_STATIC_URL, WT_MODULES_DIR, $this->getName().'/css/MarkerCluster.css" rel="stylesheet">';
		echo '<script src="', WT_STATIC_URL, WT_MODULES_DIR, $this->getName().'/js/leaflet/leaflet.markercluster.js"></script>';

		// Leaflet Fontawesome markers
		echo '<link rel="stylesheet" href="', WT_FONT_AWESOME_CSS_URL,'">';

		echo '<link rel="stylesheet" href="', WT_STATIC_URL, WT_MODULES_DIR, $this->getName().'/dist/leaflet.awesome-markers.css">';
		echo '<script src="', WT_STATIC_URL, WT_MODULES_DIR, $this->getName().'/dist/leaflet.awesome-markers.min.js"></script>';

		// Leaflet Vector-markers (there are bugs in the code of vertor-merkers, not in use)
/*		echo '<link rel="stylesheet" href="', WT_STATIC_URL, WT_MODULES_DIR, $this->getName().'/css/Leaflet.vector-markers.css">';
		echo '<script src="', WT_STATIC_URL, WT_MODULES_DIR, $this->getName().'/js/leaflet/Leaflet.vector-markers.min.js"></script>'; */
		
		require_once $this->directory.'/classes/FactPlace.php';
	}

	private function drawMap($eventsMap, $info) {
		$attributionOsmString = 'Map data © <a href=\"http://openstreetmap.org\">OpenStreetMap</a> contributors';
		$attributionMapBoxString = 'Map data &copy; <a href=\"http://openstreetmap.org\">OpenStreetMap</a> contributors | Imagery © <a href=\"http://mapbox.com\">Mapbox</a>';

		echo '
			<div id=map></div>';
		echo "
			<script>
				var osm = L.tileLayer('//{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
					attribution: '$attributionOsmString',
					maxZoom: 16
				});

				var mapbox = L.tileLayer('//{s}.tiles.mapbox.com/v3/oddityoverseer13.ino7n4nl/{z}/{x}/		{y}.png', {
					attribution: '$attributionMapBoxString',
					maxZoom: 16
				});

				var map = L.map('map').fitWorld().setZoom(2);

				osm.addTo(map);

				var baseLayers = {
					'Mapbox': mapbox,
					'OpenStreetMap': osm
				};

				L.control.layers(baseLayers).addTo(map);
				";

		// Set up markercluster
		echo "var markers = new L.MarkerClusterGroup();" . "\n";

		// Set up color and design of markers
		$colors = array('red', 'blue', 'green', 'purple', 'orange', 'darkred', 'salmon', 'beige', 'darkblue', 'darkgreen', 'cadetblue', 'darkslateblue', 'pink', 'lightblue', 'lightgreen', 'gray', 'lightgray');
		$event_options_map = array(
			'BIRT' => array('icon' => 'star'),
			'BAPM' => array('icon' => 'star'),
			'CHR'  => array('icon' => 'star'),
			'RESI' => array('icon' => 'home'),
			'CENS' => array('icon' => 'users'),
			'GRAD' => array('icon' => 'graduation-cap'),
			'OCCU' => array('icon' => 'briefcase'),
			'MARR' => array('icon' => 'object-group'),
			'DEAT' => array('icon' => 'plus-square')
			);

		$color_i = 0;
		// Populate the leaflet map with markers
		foreach($eventsMap as $xref => $personEvents) {
			// Set up polyline
			echo "var polyline = L.polyline([], {color: '" . $colors[$color_i] . "'});" . "\n";
//			usort($personEvents, array('FactPlace','CompareDate'));

			foreach($personEvents as $event) {
				if ($event->knownLatLon()) {
					$tag = $event->fact->getTag();

					// adding info to popup
					$title = ucfirst(strip_tags($info[$xref]['CloseRelationshipName'].': '.$info[$xref]['FullName'].' ('.$info[$xref]['LifeSpan'].') | '));
					$title .= strip_tags($event->shortSummary());

					$popup = '<span class="label">'.ucfirst($info[$xref]['CloseRelationshipName']).': </span>';
					$popup .= '<a href="'.$info[$xref]['HtmlUrl'].'">'.$info[$xref]['FullName'].'</a> ';
					$popup .= '('.$info[$xref]['LifeSpan'].')';
					$popup .= $event->shortSummary();

					$options = array_key_exists($tag,$event_options_map) ? $event_options_map[$tag] : array('icon' => 'circle');
					$options['markerColor'] = $colors[$color_i];
//					$test='birthday-cake';
					echo "var icon = L.AwesomeMarkers.icon({icon: '".$options['icon']."', prefix: 'fa', markerColor: '".$options['markerColor']."', iconColor: 'white'});". "\n";
//echo "var icon = L.icon({iconUrl: '/modules_v3/OSM4WebTrees/markers/marker.php'});";					
					echo "var marker = L.marker(".$event->getLatLonJSArray().", {icon: icon, title: '".$title."'});" . "\n";
					echo "marker.bindPopup('".$popup."');" . "\n";

					// Add to markercluster
					echo "markers.addLayer(marker);" . "\n";

					if ($event->fact->getDate()->isOk()) {
						// Append it to the polyline
						echo "polyline.addLatLng(".$event->getLatLonJSArray().");" . "\n";
					}
				}

				// Add polyline to map
				echo "polyline.addTo(map);" . "\n";
			}
			$color_i = ($color_i+1) % count($colors);
		}

		// Add markercluster to map
		echo "var l = map.addLayer(markers);" . "\n";

		// Zoom to bounds of polyline
		echo "map.fitBounds(markers.getBounds());" . "\n";

		echo "map.invalidateSize();" . "\n";

		echo '</script>';
	}
}

return new OpenStreetMapModule();
