<?php
namespace TYPO3\CMS\Vidi\ViewHelpers\Grid;
/***************************************************************
*  Copyright notice
*
*  (c) 2013 Fabien Udriot <fabien.udriot@typo3.org>
*  All rights reserved
*
*  This script is part of the TYPO3 project. The TYPO3 project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Vidi\Domain\Model\Content;
use TYPO3\CMS\Vidi\Tca\TcaService;

/**
 * View helper for rendering a row of a content object.
 */
class RowViewHelper extends \TYPO3\CMS\Fluid\Core\ViewHelper\AbstractViewHelper {

	/**
	 * @var array
	 */
	protected $columns = array();

	/**
	 * @param array $columns
	 */
	public function __construct($columns = array()){
		$this->columns = $columns;
	}

	/**
	 * Render a row per content object.
	 *
	 * @param \TYPO3\CMS\Vidi\Domain\Model\Content $object
	 * @param int $offset
	 * @return array
	 */
	public function render(Content $object, $offset) {

		$tcaGridService = TcaService::grid();

		// Initialize returned array
		$output = array();
		$output['DT_RowId'] = 'row-' . $object->getUid();
		#$output['DT_RowClass'] = 'row-' . $object->getStatus();

		foreach($tcaGridService->getFields() as $fieldName => $configuration) {

			if ($tcaGridService->isSystem($fieldName)) {

				$systemFieldName = substr($fieldName, 2);
				$className = sprintf('TYPO3\CMS\Vidi\ViewHelpers\Grid\System%sViewHelper', ucfirst($systemFieldName));
				if (class_exists($className)) {

					/** @var \TYPO3\CMS\Fluid\Core\ViewHelper\AbstractViewHelper $systemColumnViewHelper */
					$systemColumnViewHelper = $this->objectManager->get($className);
					$output[$fieldName] = $systemColumnViewHelper->render($object, $offset);
				}

			} elseif (!in_array($fieldName, $this->columns)) {

				// Show nothing if the column is not requested which is good for performance.
				$output[$fieldName] = '';
			} else {

				// Fetch value
				if ($tcaGridService->hasRenderers($fieldName)) {

					$result = '';
					$renderers = $tcaGridService->getRenderers($fieldName);
					foreach ($renderers as $rendererClassNameOrIndex => $rendererClassNameOrConfiguration) {

						if (is_array($rendererClassNameOrConfiguration)) {
							$rendererClassName = $rendererClassNameOrIndex;
							$gridRendererConfiguration = $rendererClassNameOrConfiguration;
						} else {
							$rendererClassName = $rendererClassNameOrConfiguration;
							$gridRendererConfiguration = array();
						}

						/** @var $rendererObject \TYPO3\CMS\Vidi\GridRenderer\GridRendererInterface */
						$rendererObject = GeneralUtility::makeInstance($rendererClassName);
						$result .= $rendererObject
							->setObject($object)
							->setFieldName($fieldName)
							->setFieldConfiguration($configuration)
							->setGridRendererConfiguration($gridRendererConfiguration)
							->render();
					}
				} else {
					$result = $object[$fieldName]; // AccessArray object

					// Avoid bad surprise, converts characters to HTML.
					$fieldType = TcaService::table($object->getDataType())->field($fieldName)->getFieldType();
					if ($fieldType !== TcaService::TEXTAREA) {
						$result = htmlentities($result); // AccessArray object
					}
				}

				$result = $this->format($result, $configuration);
				$result = $this->wrap($result, $configuration);
				$output[$fieldName] = $result;
			}
		}

		return $output;
	}

	/**
	 * Possible value formatting.
	 *
	 * @param string $value
	 * @param array $configuration
	 * @return mixed
	 */
	protected function format($value, array $configuration) {
		if (!empty($configuration['format'])) {
			$formatter = sprintf('TYPO3\CMS\Vidi\Formatter\%s::format', ucfirst($configuration['format']));
			$value = call_user_func($formatter, $value);
		}
		return $value;
	}

	/**
	 * Possible value wrapping.
	 *
	 * @param string $value
	 * @param array $configuration
	 * @return mixed
	 */
	protected function wrap($value, array $configuration) {
		if (!empty($configuration['wrap'])) {
			$parts = explode('|', $configuration['wrap']);
			$value = implode($value, $parts);
		}
		return $value;
	}
}

?>