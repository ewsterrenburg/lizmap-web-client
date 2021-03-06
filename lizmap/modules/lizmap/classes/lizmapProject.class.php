<?php
/**
* Manage and give access to lizmap configuration.
* @package   lizmap
* @subpackage lizmap
* @author    3liz
* @copyright 2012 3liz
* @link      http://3liz.com
* @license Mozilla Public License : http://www.mozilla.org/MPL/
*/


class lizmapProject{

    // lizmapRepository
    private $repository = null;
    // QGIS project XML
    private $xml = null;
    // CFG project JSON
    private $cfg = null;

    // services properties
    private $properties = array(
      'repository',
      'id',
      'title',
      'abstract',
      'proj',
      'bbox'
    );
    // Lizmap repository key
    private $key = '';
    // Lizmap repository configuration data
    private $data = array();

    /**
     * constructor
     * key : the project name
     * rep : the repository has a lizmapRepository class
     */
    public function __construct ( $key, $rep ) {
      if (file_exists($rep->getPath().$key.'.qgs')
       && file_exists($rep->getPath().$key.'.qgs.cfg') ) {
        $this->key = $key;
        $this->repository = $rep;

        $key_session = $rep->getKey().'~'.$key;
        $qgs_path = $rep->getPath().$key.'.qgs';
        $config = null;
        $qgs_xml = null;
        $update_session = false;

        if ( isset($_SESSION['_LIZMAP_']) 
          && isset($_SESSION['_LIZMAP_'][$key_session])
          && isset($_SESSION['_LIZMAP_'][$key_session]['cfg'])
          && isset($_SESSION['_LIZMAP_'][$key_session]['cfgmtime'])
          && $_SESSION['_LIZMAP_'][$key_session]['cfgmtime'] >= filemtime($qgs_path.'.cfg')
          )
          $config = $_SESSION['_LIZMAP_'][$key_session]['cfg'];
        else {
          $config = jFile::read($qgs_path.'.cfg');
          $update_session = true;
        }
        $this->cfg = json_decode($config);
        $configOptions = $this->cfg->options;

        if ( isset($_SESSION['_LIZMAP_']) 
          && isset($_SESSION['_LIZMAP_'][$key_session])
          && isset($_SESSION['_LIZMAP_'][$key_session]['xml'])
          && isset($_SESSION['_LIZMAP_'][$key_session]['xmlmtime'])
          && $_SESSION['_LIZMAP_'][$key_session]['xmlmtime'] >= filemtime($qgs_path)
          )
          $qgs_xml = simplexml_load_string($_SESSION['_LIZMAP_'][$key_session]['xml']);
        else {
          $qgs_xml = simplexml_load_file($qgs_path);
          $update_session = true;
        }
        $this->xml = $qgs_xml;

        $this->data = array(
          'repository'=>$rep->getKey(),
          'id'=>$key,
          'title'=>ucfirst($key),
          'abstract'=>'',
          'proj'=> $configOptions->projection->ref,
          'bbox'=> join($configOptions->bbox,', ')
        );
        # get title from WMS properties
        if (property_exists($qgs_xml->properties, 'WMSServiceTitle'))
          if (!empty($qgs_xml->properties->WMSServiceTitle))
            $this->data['title'] = $qgs_xml->properties->WMSServiceTitle;
        # get abstract from WMS properties
        if (property_exists($qgs_xml->properties, 'WMSServiceAbstract'))
          $this->data['abstract'] = $qgs_xml->properties->WMSServiceAbstract;
        if ( $update_session ) {
          if ( !isset($_SESSION['_LIZMAP_']) )
            $_SESSION['_LIZMAP_'] = array($key_session=>array());
          else if ( !isset($_SESSION['_LIZMAP_'][$key_session]) )
            $_SESSION['_LIZMAP_'][$key_session] = array();
          $_SESSION['_LIZMAP_'][$key_session]['xml'] = $qgs_xml->saveXml();
          $_SESSION['_LIZMAP_'][$key_session]['xmlmtime'] = filemtime($qgs_path);
          $_SESSION['_LIZMAP_'][$key_session]['cfg'] = $config;
          $_SESSION['_LIZMAP_'][$key_session]['cfgmtime'] = filemtime($qgs_path.'.cfg');
        }
      }
    }

    public function getKey(){
      return $this->key;
    }
    
    public function getRepository(){
      return $this->repository;
    }
    
    public function getProperties(){
      return $this->properties;
    }

    public function getData( $key ) {
      if ( !array_key_exists($key, $this->data) )
        return null;
      return $this->data[$key];
    }
    
    public function getOptions(){
      return $this->cfg->options;
    }
    
    public function getLayers(){
      return $this->cfg->layers;
    }
    
    public function hasLocateByLayer(){
      if ( property_exists($this->cfg,'locateByLayer') ){
        $count = 0;
        foreach( $this->cfg->locateByLayer as $key=>$obj ){
          $count += 1;
        }
        if ( $count != 0 )
          return true;
        return false;
      }
      return false;
    }
    
    public function hasEditionLayers(){
      if ( property_exists($this->cfg,'editionLayers') ){
        if(!jacl2::check('lizmap.tools.edition.use', $this->repository->getKey()))
          return false;

        $count = 0;
        foreach( $this->cfg->editionLayers as $key=>$obj ){
          $count += 1;
        }
        if ( $count != 0 )
          return true;
        return false;
      }
      return false;
    }

    public function hasLoginFilteredLayers(){
      if ( property_exists($this->cfg,'loginFilteredLayers') ){
        $count = 0;
        foreach( $this->cfg->loginFilteredLayers as $key=>$obj ){
          $count += 1;
        }
        if ( $count != 0 )
          return true;
        return false;
      }
      return false;
    }

    public function needsGoogle(){
      $configOptions = $this->cfg->options;
      return (
        (
          property_exists($configOptions,'googleStreets')
          && $configOptions->googleStreets == 'True'
        ) ||
        (
          property_exists($configOptions,'googleSatellite')
          && $configOptions->googleSatellite == 'True'
        ) ||
        (
          property_exists($configOptions,'googleHybrid')
          && $configOptions->googleHybrid == 'True'
        ) ||
        (
          property_exists($configOptions,'googleTerrain')
          && $configOptions->googleTerrain == 'True'
        )
      );
    }
    
    public function getGoogleKey(){
        $configOptions = $this->cfg->options;
        $gkey = '';
        if (property_exists($configOptions,'googleKey')
          && $configOptions->googleKey != '')
          $gkey = $configOptions->googleKey;
      return $gkey;
    }

    public function getWMSInformation(){
      $qgsLoad = $this->xml;

      // Default metadata
      $WMSServiceTitle = '';
      $WMSServiceAbstract = '';
      $WMSExtent = '';
      $ProjectCrs = '';
      $WMSOnlineResource = '';
      $WMSContactMail = '';
      $WMSContactOrganization = '';
      $WMSContactPerson = '';
      $WMSContactPhone = '';
      if($qgsLoad){
        $WMSServiceTitle = (string)$qgsLoad->properties->WMSServiceTitle;
        $WMSServiceAbstract = (string)$qgsLoad->properties->WMSServiceAbstract;
        $WMSServiceAbstract = nl2br($WMSServiceAbstract);
        $WMSExtent = $qgsLoad->properties->WMSExtent->value[0];
        $WMSExtent.= ", ".$qgsLoad->properties->WMSExtent->value[1];
        $WMSExtent.= ", ".$qgsLoad->properties->WMSExtent->value[2];
        $WMSExtent.= ", ".$qgsLoad->properties->WMSExtent->value[3];
        $ProjectCrs = (string)$qgsLoad->properties->SpatialRefSys->ProjectCrs;
        $WMSOnlineResource = (string)$qgsLoad->properties->WMSOnlineResource;
        $WMSContactMail = (string)$qgsLoad->properties->WMSContactMail;
        $WMSContactOrganization = (string)$qgsLoad->properties->WMSContactOrganization;
        $WMSContactPerson= (string)$qgsLoad->properties->WMSContactPerson;
        $WMSContactPhone = (string)$qgsLoad->properties->WMSContactPhone;
      }

      return array(
        'WMSServiceTitle'=>$WMSServiceTitle,
        'WMSServiceAbstract'=>$WMSServiceAbstract,
        'WMSExtent'=>$WMSExtent,
        'ProjectCrs'=>$ProjectCrs,
        'WMSOnlineResource'=>$WMSOnlineResource,
        'WMSContactMail'=>$WMSContactMail,
        'WMSContactOrganization'=>$WMSContactOrganization,
        'WMSContactPerson'=>$WMSContactPerson,
        'WMSContactPhone'=>$WMSContactPhone
      );
    }

    public function getUpdatedConfig(){
      $qgsLoad = $this->xml;

      $legend = $qgsLoad->xpath('//legend');
      $legendZero = $legend[0];
      $updateDrawingOrder = (string)$legendZero->attributes()->updateDrawingOrder;

      $layersOrder = array();  
      if($updateDrawingOrder == 'false'){
        $layers =  $qgsLoad->xpath('//legendlayer');
        foreach($layers as $layer){
          if($layer->attributes()->drawingOrder and $layer->attributes()->drawingOrder >= 0){
            $layersOrder[(string)$layer->attributes()->name] = (integer)$layer->attributes()->drawingOrder;
          }
        }
      }   

      $configRead = json_encode($this->cfg);
      $configJson = json_decode($configRead);
      
      // Add an option to display buttons to remove the cache for cached layer
      // Only if appropriate right is found
      if( jacl2::check('lizmap.admin.repositories.delete') ){
        $configJson->options->removeCache = 'True';
      }
      
      // Remove layerOrder option from config if not required 
      if(!empty($layersOrder)){
        $configJson->layersOrder = $layersOrder;
      }
      
      // Remove FTP remote directory
      if(property_exists($configJson->options, 'remoteDir'))
        unset($configJson->options->remoteDir);

      // Remove editionLayers from config if no right to access this tool
      // Or if no ability to load spatialite extension
      if(jacl2::check('lizmap.tools.edition.use', $this->repository->getKey())){
        $spatial = false;
        if ( class_exists('SQLite3') ) {
          try{
            $db = new SQLite3(':memory:');
            $spatial = $db->loadExtension('libspatialite.so'); # loading SpatiaLite as an extension
          }catch(Exception $e){
            $spatial = False;
          }
        }
        if(!$spatial){
          foreach( $configJson->editionLayers as $key=>$obj ){
            $layerXml = $this->getXmlLayer( $obj->layerId );
            $layerXmlZero = $layerXml[0];
            $provider = $layerXmlZero->xpath('provider');
            $provider = (string)$provider[0];
            if ( $provider == 'spatialite' )
              unset($configJson->editionLayers->$key);
          }
        }
      } else {
        unset($configJson->editionLayers);
      }
      $configRead = json_encode($configJson);

      return $configRead;
    }

    public function getCanvasColor(){
      $red = $this->xml->xpath( "//properties/Gui/CanvasColorRedPart" );
      $green = $this->xml->xpath( "//properties/Gui/CanvasColorGreenPart" );
      $blue = $this->xml->xpath( "//properties/Gui/CanvasColorBluePart" );
      return 'rgb('.$red[0].','.$green[0].','.$blue[0].')';
    }

    public function getProj4( $authId ){
      return $this->xml->xpath( "//spatialrefsys/authid[.='".$authId."']/parent::*/proj4" );
    }

    public function getFullCfg(){
      return $this->cfg;
    }

    public function getXmlLayer( $layerId ){
      return $this->xml->xpath( "//maplayer[id='$layerId']" );
    }

    public function getXmlLayerByKeyword( $key ){
      return $this->xml->xpath( "//maplayer/keywordList[value='$key']/parent::*" );
    }

    public function getComposer( $title ){
      $xmlComposer = $this->xml->xpath( "//Composer[@title='$title']" );
      if( $xmlComposer )
        return $xmlComposer[0];
      else
        return null;
    }

    public function getLayer( $layerId ){
      $xmlLayer = $this->xml->xpath( "//maplayer[id='$layerId']" );
      if( $xmlLayer ) {
        $xmlLayer = $xmlLayer[0];
        jClasses::inc('lizmap~qgisMapLayer');
        jClasses::inc('lizmap~qgisVectorLayer');
        if( $xmlLayer->attributes()->type == 'vector' )
          return new qgisVectorLayer( $this, $xmlLayer );
        else
          return new qgisMapLayer( $this, $xmlLayer );
      }
      return null;
    }

    public function getLayerByKeyword( $key ){
      $xmlLayer = $this->xml->xpath( "//maplayer/keywordList[value='$key']/parent::*" );
      if( $xmlLayer ) {
        $xmlLayer = $xmlLayer[0];
        jClasses::inc('lizmap~qgisMapLayer');
        jClasses::inc('lizmap~qgisVectorLayer');
        if( $xmlLayer->attributes()->type == 'vector' )
          return new qgisVectorLayer( $this, $xmlLayer );
        else
          return new qgisMapLayer( $this, $xmlLayer );
      }
      return null;
    }
}
