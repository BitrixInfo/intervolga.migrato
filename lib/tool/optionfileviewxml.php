<?namespace Intervolga\Migrato\Tool;

use Bitrix\Main\IO\File;

class OptionFileViewXml
{
	/**
	 * @param array $options
	 * @param string $directory
	 * @param string $module
	 */
	public static function write(array $options, $directory, $module)
	{
		$arModuleVersion = array('VERSION' => '');
		include dirname(dirname(__DIR__)) . '/install/version.php';
		$content = "";
		$content .= XmlHelper::xmlHeader();
		$content .= "<options migrato=\"" . $arModuleVersion["VERSION"] . "\">\n";
		foreach ($options as $option)
		{
			$map = array(
				'name' => $option['NAME'],
				'value' => $option['VALUE'],
				'site' => $option['SITE_ID'],
			);
			$optionXml = XmlHelper::tagMap('option', $map, 1);
			if ($option['DEPENDENCY'])
			{
				$optionXml = XmlHelper::addAttrToTags('option', array('dependency' => $option['DEPENDENCY']), $optionXml);
			}
			$content .= $optionXml;
		}
		$content .= '</options>';

		File::putFileContents($directory . $module . '.xml', $content);
	}

	/**
	 * @param string $path
	 * @return array
	 */
	public static function readFromFileSystem($path)
	{
		$options = array();
		$xmlParser = new \CDataXML();
		$xmlParser->Load($path);
		$xmlArray = $xmlParser->getArray();
		foreach ($xmlArray['options']['#']['option'] as $optionArray)
		{
			$options[] = array(
				'NAME' => $optionArray['#']['name'][0]['#'],
				'VALUE' => $optionArray['#']['value'][0]['#'],
				'SITE_ID' => $optionArray['#']['site'][0]['#'],
			);
		}

		return $options;
	}
}