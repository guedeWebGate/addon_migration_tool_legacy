<?php

class MigrationBatchExporter
{

	protected $batch;
	protected $parsed = false;
	protected $xmlValue;

	public function __construct(MigrationBatch $batch)
	{
		$this->batch = $batch;
	}

	protected function parse()
	{
		Loader::library('content/exporter');
		$x = new SimpleXMLElement("<?xml version=\"1.0\" encoding=\"UTF-8\"?><concrete5-cif></concrete5-cif>");
		$x->addAttribute('version', '1.0');

		$pages = $this->batch->getPages();
		if (count($pages)) {
			$top = $x->addChild('pages');
			foreach($pages as $c) {
				$c->export($top);
			}
		}
		$this->xmlValue = $x->asXML();
		$this->parsed = true;
	}

	public function getContentXML()
	{
		if (!$this->parsed) {
			$this->parse();
		}
		return $this->xmlValue;
	}

	/**
	 * Loops through all pages and returns files referenced.
	 */
	public function getReferencedFiles()
	{
		if (!$this->parsed) {
			$this->parse();
		}

		$regExp = '/\{ccm:export:file:(.*?)\}|\{ccm:export:image:(.*?)\}/i';
		$items = array();
		if (preg_match_all(
			$regExp,
			$this->getContentXML(),
			$matches
		)
		) {
			if (count($matches)) {
				for ($i = 1; $i < count($matches); $i++ ) {
					$results = $matches[$i];
					foreach($results as $reference) {
						if ($reference) {
							$items[] = $reference;
						}
					}
				}
			}
		}
		$files = array();
		$db = Loader::db();
		foreach($items as $item) {
			$db = Loader::db();
			$fID = $db->GetOne('select fID from FileVersions where fvFilename = ?', array($item));
			if ($fID) {
				$f = File::getByID($fID);
				if (is_object($f) && !$f->isError()) {
					$files[] = $f;
				}
			}
		}
		return $files;
	}

	public function isExported() {
		$filePath = $this->getFilePath();
		$fileName = $this->buildFileName($filePath);
		return file_exists($fileName);
	}

	public function saveToFileSystem() {
		if (!$this->parsed) {
			$this->parse();
		}
		$xml = $this->getContentXML();
		Log::addEntry('Save '. $xml . ' to file');
		$filePath = $this->getFilePath();
		$fileName = $this->buildFileName($filePath);
		$filecurrent = fopen($fileName,'w' );
		fwrite($filecurrent, $xml);
		fclose($filecurrent);
	}

	public function loadFromFileSystem() {
		$filePath = $this->getFilePath();
		$fileName = $this->buildFileName($filePath);
		$fileCurrent = fopen($fileName, 'r');
		$this->xmlValue = fread($fileCurrent, filesize($fileName));
		$this->parsed = true;
	}

	public function clearFromFileSystem() {
		$filePath = $this->getFilePath();
		$fileName = $this->buildFileName($filePath);
		unlink($fileName);		
	}

	private function getFilePath() {
		$fh = Loader::helper('file');

		$path = $fh->getTemporaryDirectory();
		$filePath = $path .'/migrationTool';
		if (!is_dir($filePath)) {
			mkdir($filePath);
		}
		return $filePath;		
	}

	private function buildFileName($pathCurrent) {
		return $pathCurrent .'/' . $this->batch->getID() .'.xml';
	}

}