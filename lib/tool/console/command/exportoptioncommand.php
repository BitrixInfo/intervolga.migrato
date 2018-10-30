<?namespace Intervolga\Migrato\Tool\Console\Command;

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\IO\Directory;
use Bitrix\Main\SystemException;
use Intervolga\Migrato\Data\RecordId;
use Intervolga\Migrato\Tool\Config;
use Intervolga\Migrato\Tool\Console\Logger;
use Intervolga\Migrato\Tool\OptionFileViewXml;
use Intervolga\Migrato\Tool\Orm\OptionTable;

Loc::loadMessages(__FILE__);

class ExportOptionCommand extends BaseCommand
{
	const NO_SITE = '00';
	protected $options = array();
	
	protected function configure()
	{
		$this->setName('exportoptions');
		$this->setHidden(true);
		$this->setDescription(Loc::getMessage('INTERVOLGA_MIGRATO.EXPORT_OPTIONS_DESCRIPTION'));
	}

	public function executeInner()
	{
		$this->clearDirOptions();
		foreach ($this->getDbOptions() as $module => $moduleOptions)
		{
			if ($moduleOptions)
			{
				OptionFileViewXml::write($moduleOptions, INTERVOLGA_MIGRATO_DIRECTORY . 'options/' , $module);
				foreach ($moduleOptions as $moduleOption)
				{
					$this->logger->addDb(
						array(
							'MODULE_NAME' => $module,
							'ENTITY_NAME' => 'option',
							'ID' => RecordId::createComplexId(array(
								'SITE_ID' => $moduleOption['SITE_ID'],
								'NAME' => $moduleOption['NAME'],
							)),
							'OPERATION' => Loc::getMessage('INTERVOLGA_MIGRATO.EXPORT_OPTION'),
						),
						Logger::TYPE_OK
					);
				}
			}
		}
	}

	protected function clearDirOptions()
	{
		$path = INTERVOLGA_MIGRATO_DIRECTORY . 'options';
		if (Directory::isDirectoryExists($path))
		{
			Directory::deleteDirectory($path);
		}
	}

	/**
	 * @param bool $force
	 *
	 * @throws \Bitrix\Main\ArgumentException
	 */
	protected function loadDbOptions($force = false)
	{
		if (!$this->options || $force)
		{
			$this->options = array();
			$getList = OptionTable::getList(array(
				'select' => array(
					'MODULE_ID',
					'NAME',
					'VALUE',
					'SITE_ID',
				),
			));
			while ($option = $getList->fetch())
			{
				if (!$option['SITE_ID'])
				{
					$option['SITE_ID'] = static::NO_SITE;
				}
				$this->options[$option['MODULE_ID']][$option['NAME']][$option['SITE_ID']] = $option;
			}
		}
	}

	/**
	 * @return array
	 */
	protected function getDbOptions()
	{
		$this->loadDbOptions();
		$options = array();
		foreach ($this->options as $moduleId => $moduleOptions)
		{
			foreach ($moduleOptions as $name => $sameOptions)
			{
				if (Config::getInstance()->isOptionIncluded($moduleId, $name))
				{
					$arDependency = Config::getInstance()->getDependency($moduleId, $name);
					foreach ($sameOptions as $siteId => $option)
					{
						try
						{
							if($option['VALUE'] && $arDependency)
							{
								$option['VALUE'] = static::getDependencyXmlValue($option, $arDependency);
								$option['DEPENDENCY'] = $arDependency['entityModule'] . ':' . $arDependency['entity'];
							}

							if ($option['NAME'])
							{
								$siteId = $option['SITE_ID'];
								if ($option['SITE_ID'] == static::NO_SITE)
								{
									unset($option['SITE_ID']);
								}
								$options[$moduleId][$option['NAME'] . '.' . $siteId] = $option;
							}
						}
						catch (\Exception $exception)
						{
							$this->logger->addDb(
								array(
									'MODULE_NAME' => $moduleId,
									'ENTITY_NAME' => 'option',
									'ID' => RecordId::createComplexId(array(
										'SITE_ID' => $siteId,
										'NAME' => $option['NAME'],
									)),
									"EXCEPTION" => $exception,
									'OPERATION' => Loc::getMessage('INTERVOLGA_MIGRATO.EXPORT_OPTION'),
								),
								Logger::TYPE_FAIL
							);
						}
					}
				}
				ksort($options[$moduleId]);
			}
		}
		return $options;
	}

	/**
	 * @param $option
	 * @param $dependency
	 * @return string
	 * @throws SystemException
	 */
	protected static function getDependencyXmlValue($option, $dependency)
	{
		if ($entity = Config::getInstance()->getDataClass($dependency['entityModule'], $dependency['entity']))
		{
			$value = $entity::getInstance()->getXmlId(RecordId::createStringId($option["VALUE"]));
			if ($value)
			{
				return $value;
			}
			else
			{
				throw new \Exception(str_replace('#OPTION#', $option['NAME'], Loc::getMessage('INTERVOLGA_MIGRATO.EXPORT_OPTION_DEPENDENCY_NO_FOUND')));
			}
		}
		throw new \Exception(str_replace(array('#ENTITY#', '#OPTION#'), array($dependency['entity'], $option['NAME']), Loc::getMessage('INTERVOLGA_MIGRATO.OPTION_DEPENDENCY_ENTITY_ERROR')));
	}
}