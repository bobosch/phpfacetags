<?php
class FaceTags {
	/**
	 * This function creates all face thumbnails from a file name
	 *
	 * @return array
	 */
	public function createFaceThumbnails($filename,$width=false) {
		$faces=$this->getFaceDataFromFile($filename);

		if($faces) {
			$this->readFileInformation($filename,$image,$info,$exif);
			$path_parts=pathinfo($filename);
			$base=$path_parts['dirname'].'/'.$path_parts['filename'].' ';

			$rects=array();
			foreach($faces as $name=>$face) {
				$thumbname=$base.strtr($name,array('/'=>'_')).'.jpg';
				$rects[$name]=$this->createThumbnail($face,$image,$info,$exif,$thumbname,$width);
			}

			return $rects;
		}
	}
	
	/**
	 * This function creates a single face thumbnail
	 */
	public function createFaceThumbnail($filename,$face,$thumbname,$width=false) {
		$this->readFileInformation($filename,$image,$info,$exif);
		$this->createThumbnail($face,$image,$info,$exif,$thumbname,$width);
	}
	
	/**
	 * This function reads file information for thumbnail generation
	 */
	public function readFileInformation($filename,&$image,&$info,&$exif) {
		$image=imagecreatefromjpeg($filename);
		$info=array(
			'x'=>imagesx($image),
			'y'=>imagesy($image),
		);
		$exif=exif_read_data($filename);
	}
	
	/**
	 * This function creates the thumbnail
	 *
	 * @return array
	 */
	public function createThumbnail($face,$image,$info,$exif,$thumbname,$width=false) {
		$rect=$this->getFaceCoordinates($face,$info);
		// Create thumbnail
		if($rect) {
			$crop=imagecrop($image,$rect);
			$crop=$this->rotateImageByExif($crop,$exif);
			if($width) {
				$thumb=imagescale($crop,$width);
				imagejpeg($thumb,$thumbname);
			} else {
				imagejpeg($crop,$thumbname);
			}
		}
		return $rect;
	}
	
	/**
	 * This function returns face information from a file
	 *
	 * @return array
	 */
	public function getFaceDataFromFile($filename) {
		// Does the image have a XMP tag?
		$xmp=$this->getXMPTagFromFile($filename);
		if($xmp) {
			// Get face data from XMP tag
			return $this->getFaceDataFromXMPTag($xmp);
		}
	}

	/**
	 * This function extracts the XMP tag from a file
	 *
	 * @return string
	 */
	public function getXMPTagFromFile($filename) {
		$content=file_get_contents($filename);
		$xmp_start=strpos($content, '<x:xmpmeta');
		$xmp_end=strpos($content, '</x:xmpmeta>');
		$xmp_length=$xmp_end - $xmp_start;
		$xmp=substr($content, $xmp_start, $xmp_length + 12);
		
		return $xmp;
	}
	
	/**
	 * This function returns face information from a XMP tag
	 *
	 * @return array
	 */
	public function getFaceDataFromXMPTag($xmp) {
		$faces=array();
		$name='';

		$xml_parser=xml_parser_create('UTF-8');
		xml_parser_set_option($xml_parser,XML_OPTION_SKIP_WHITE,0);
		xml_parser_set_option($xml_parser,XML_OPTION_CASE_FOLDING,0);
		xml_parse_into_struct($xml_parser, $xmp, $vals, $index);
		xml_parser_free($xml_parser);
		
		foreach($vals as $val) {
			switch($val['tag']){
				case 'rdf:Description':
					if($val['type']=='open' && !empty($val['attributes']['mwg-rs:Name']) && $val['attributes']['mwg-rs:Type']=='Face') {
						$name=trim($val['attributes']['mwg-rs:Name']);
					}
					if($val['type']=='close') {
						$name='';
					}
					break;
				case 'mwg-rs:Area':
					if($val['type']='complete' && $name) {
						$faces[$name]=$val['attributes'];
					}
					break;
			}
		}
		
		return $faces;
	}

	/**
	 * This function converts XMP coordinates to pixels
	 *
	 * @return array
	 */
	public function getFaceCoordinates($face,$info) {
		if($face['stArea:unit']=='normalized') {
			$rect=array(
				'x'=>intval(round($info['x']*($face['stArea:x']-$face['stArea:w']/2))),
				'y'=>intval(round($info['y']*($face['stArea:y']-$face['stArea:h']/2))),
				'width'=>intval(round($info['x']*$face['stArea:w'])),
				'height'=>intval(round($info['y']*$face['stArea:h'])),
			);

			return $rect;
		}
	}

	public function rotateImageByExif($image,$exif) {
		if(isset($exif['Orientation'])) {
			switch($exif['Orientation']) {
				case 2:
					imageflip($image,IMG_FLIP_VERTICAL);
					break;
				case 3:
					$image=imagerotate($image,180,0);
					break;
				case 4:
					imageflip($image,IMG_FLIP_HORIZONTAL);
					break;
				case 5:
					$image=imagerotate($image,270,0);
					imageflip($image,IMG_FLIP_VERTICAL);
					break;
				case 6:
					$image=imagerotate($image,270,0);
					break;
				case 7:
					$image=imagerotate($image,90,0);
					imageflip($image,IMG_FLIP_VERTICAL);
					break;
				case 8:
					$image=imagerotate($image,90,0);
					break;
			}
		}
		
		return $image;
	}
}
?>
