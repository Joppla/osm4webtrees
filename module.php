<?php # script module.php

/***************************************************************
 * filename:	module.php
 * made by:		Joppla
 * contact: 	https://github.com/Joppla/osm4webtrees/
 * version: 	PreRel-0.03
 * last mod.:	24 III 2017
 *
 * Description of Module:
 * see: /README.md
 *
 * Files:
 * -------------------
 * /classes/FactPlace.php   classes
 * /css/osm-module.css      stylesheet of the module
 * /css/images/ 				 images for css
 *
 * Dependencies:
 * -------------------
 * Leaflet
 * version: 1.0.3
 * map:		/Leaflet/
 * source:	http://leafletjs.com
 *
 * Leaflet.markercluster
 * version: 1.0.4
 * map:		/Leaflet.markercluster/
 * source:	https://github.com/Leaflet/Leaflet.markercluster
 *
 ****************************************************************/

namespace Joppla\WebtreesModules\OpenStreetMapModule;

use Composer\Autoload\ClassLoader;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Functions\Functions;
use Fisharebest\Webtrees\Filter;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Module\AbstractModule;
use Fisharebest\Webtrees\Module\ModuleTabInterface;
// use Joppla\WebtreesModules\OpenStreetMapModule\classes;


class OpenStreetMapModule extends AbstractModule implements ModuleTabInterface {
	const CUSTOM_VERSION	 = 'PreRel-0.03';
	const CUSTOM_WEBSITE	 = 'https://github.com/Joppla/osm4webtrees/';

	var $directory;
	var $action;

	public function __construct()
	{
		parent::__construct('OpenStreetMapModule');
		$this->directory = WT_MODULES_DIR . $this->getName();
		$this->action = Filter::get('mod_action');
		// register the namespaces
		$loader = new ClassLoader();
		$loader->addPsr4('Joppla\\WebtreesModules\\OpenStreetMapModule\\', $this->directory , '/classes');
		$loader->register();
	}

/*	private function module(){
		return new FactPlace;
	}*/
	
	// Extend AbstractModule.
	// Unique internal name for this module. Must match the directory name
	public function getName() {
		return "osm4webtrees";
	}

	// Extend AbstractModule.
	// This title should be normalized when this module will be added officially
	public function getTitle() {
		return /* I18N: Name of a module */ I18N::translate('OpenStreetMap');
	}

	// Extend AbstractModule
	// This gives the description of the module, or 'title' if mouse is going over tab.
	public function getDescription() {
		return /* I18N: Description of the “OSM” module */ 
			I18N::translate('Show the location of places, events and the links between them using OpenStreetMap (OSM)');
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
		$this->individualMap();
	}

	// Implement ModuleTabInterface
	public function hasTabContent() {
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

	protected function includeCss($css) {
			return
				'<script>
					var newSheet=document.createElement("link");
					newSheet.setAttribute("href","' . $css . '");
					newSheet.setAttribute("type","text/css");
					newSheet.setAttribute("rel","stylesheet");
					newSheet.setAttribute("media","all");
					document.getElementsByTagName("head")[0].appendChild(newSheet);
				</script>';

	}


	private function includes($controller) {

		// includes JS: Leaflet and Leaflet.markercluster
		echo '<script src="'. $this->directory .'/Leaflet/leaflet.js"></script>';
		echo '<script src="'. $this->directory .'/Leaflet.markercluster/leaflet.markercluster.js"></script>';

		// includes CSS: Leaflet and Leaflet.markercluster
		echo $this->includeCss($this->directory . '/Leaflet/leaflet.css');
		echo $this->includeCss($this->directory . '/Leaflet.markercluster/MarkerCluster.Default.css'); //default
		echo $this->includeCss($this->directory . '/Leaflet.markercluster/MarkerCluster.css'); // user designed

		// includes CSS: for styling the map
		echo $this->includeCss($this->directory . '/css/osm-module.css');

		// includes the php for extra classes
		// in my opinion can this be done in another way
//		require_once $this->directory.'/classes/FactPlace.php';

	} // end of private function includes()


	private function drawMap($eventsMap, $info) {
		$attributionOsmString = 'Map data © <a href=\"https://openstreetmap.org\">OpenStreetMap</a> contributors';
		$attributionMapBoxString = 'Map data &copy; <a href=\"https://openstreetmap.org\">OpenStreetMap</a> contributors | Imagery © <a href=\"http://mapbox.com\">Mapbox</a>';

		echo '
		
			<div id="osm-map"></div>
			';
		

		// setup kind of map
		echo "
			<script>
				var osm = L.tileLayer('//{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
				{
					attribution: '$attributionOsmString',
					maxZoom: 18
				});

				var mapbox = L.tileLayer('//{s}.tiles.mapbox.com/v3/oddityoverseer13.ino7n4nl/{z}/{x}/		{y}.png',
				{
					attribution: '$attributionMapBoxString',
					maxZoom: 18
				});

				var map = L.map('osm-map').fitWorld().setZoom(10);

				osm.addTo(map);

				var baseLayers = {
					'Mapbox': mapbox,
					'OpenStreetMap': osm
				};

				L.control.layers(baseLayers).addTo(map);
			";

		// Set up markercluster
		echo "
				var markers = new L.MarkerClusterGroup({maxClusterRadius: 20});
			";

		// Set up color and design of markers
		$colors = array(
			'red', 'blue', 'green', 'purple', 'orange', 'darkred', 'salmon', 'beige', 'darkblue',
			'darkgreen', 'cadetblue', 'darkslateblue', 'pink', 'lightblue', 'lightgreen', 'gray', 'lightgray');
		// Set up kind of markers
		$event_options_map = array(
			'BIRT' => 'icon-birt',
			'BAPM' => 'icon-birt',
			'CHR'  => 'icon-birt',
			'RESI' => 'icon-resi',
			'OCCU' => 'icon-occu',
			'MARR' => 'icon-marr',
			'DEAT' => 'icon-deat'
			);

		//counter for color to zero
		$color_i = 0;

		// Populate the leaflet map with markers
		foreach($eventsMap as $xref => $personEvents) {
			// Set up polyline
			echo "var polyline = L.polyline([], {color: '" . $colors[$color_i] . "'});" . "\n";

			foreach($personEvents as $event) {
				if ($event->knownLatLon()) {
					$tag = $event->fact->getTag();

					// adding info to popup
					$title = ucfirst(strip_tags($info[$xref]['CloseRelationshipName'].': '
						.$info[$xref]['FullName'].' ('.$info[$xref]['LifeSpan'].') | '));
					$title .= strip_tags($event->shortSummary());

					$popup = '<span class="label">'.ucfirst($info[$xref]['CloseRelationshipName']).': </span>';
					$popup .= '<a href="'.$info[$xref]['HtmlUrl'].'">'.$info[$xref]['FullName'].'</a> ';
					$popup .= '('.$info[$xref]['LifeSpan'].')';
					$popup .= $event->shortSummary();

					$className = array_key_exists($tag,$event_options_map) ? $event_options_map[$tag] : 'icon-star';
//					$options['markerColor'] = $colors[$color_i];

					echo '
					var myHtml = \'<svg xmlns="http://www.w3.org/2000/svg" version="1.1" class="svg-icon-svg" width="25" height="50"><path class="svg-icon-path" d="M 0.5 13 C 0.5 25 12.5 20 12.5 49.5 C 12.5 20 24.5 25 24.5 12.5 A 6.25 6.25 0 0 0 0.5 12.5 M 2.5 12.5 A 10 10 0 0 1 22.5 12.5 A 10 10 0 0 1 2.5 12.5 Z" stroke-width="1" stroke="'.$colors[$color_i].'" fill="'.$colors[$color_i].'"></path></svg>\';

					var myIcon = L.divIcon({className: \'my-div-icon '.$className.'\', iconSize: [25,50], iconAnchor: [12.5,50], popupAnchor: [0,-50], html: myHtml });
					';

					echo "var marker = L.marker(".$event->getLatLonJSArray().", { icon: myIcon, title: '".$title."'});" . "\n";



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

		echo "var myBounds = markers.getBounds();";
		

		// Zoom to bounds of polyline
//		echo "map.fitBounds(myBounds,{maxZoom:10});" . "\n";
//		echo "map.panInsideBounds(markers.getBounds());" . "\n";

//		echo "map.Zoom(10);"; 
		echo "map.setView(myBounds.getCenter(),10);";
//				echo "map.fitWorld().setZoom(5);";
		echo "map.invalidateSize();" . "\n";


		echo '</script>';
	} // end of function drawMap()

} // end of class OpenStreetMapModule()


return new OpenStreetMapModule();
