<?php
namespace Yandex\Market\Components;

use Bitrix\Main;
use Yandex\Market;

if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) { die(); }

class AdminFormEdit extends \CBitrixComponent
{
    const LANG_PREFIX = 'YANDEX_MARKET_FORM_EDIT_';

    /** @var \Yandex\Market\Component\Base\EditForm */
    private $provider;

    public function onPrepareComponentParams($arParams)
    {
        $arParams['FORM_ID'] = trim($arParams['FORM_ID']);
        $arParams['TITLE'] = trim($arParams['TITLE']);
        $arParams['TITLE_ADD'] = trim($arParams['TITLE_ADD']);
        $arParams['BTN_SAVE'] = trim($arParams['BTN_SAVE']);
        $arParams['BTN_APPLY'] = trim($arParams['BTN_APPLY']);
        $arParams['LIST_URL'] = trim($arParams['LIST_URL']);
        $arParams['SAVE_URL'] = trim($arParams['SAVE_URL']);
        $arParams['CONTEXT_MENU'] = (array)$arParams['CONTEXT_MENU'];
        $arParams['TABS'] = (array)$arParams['TABS'];
        $arParams['FORM_BEHAVIOR'] = ($arParams['FORM_BEHAVIOR'] === 'steps' ? 'steps' : 'tabs');
        $arParams['COPY'] = (bool)$arParams['COPY'];
	    $arParams['ALLOW_SAVE'] = !isset($arParams['ALLOW_SAVE']) || $arParams['ALLOW_SAVE'];
	    $arParams['SAVE_PARTIALLY'] = isset($arParams['SAVE_PARTIALLY']) && $arParams['SAVE_PARTIALLY'];

	    /** @var class-string<Market\Component\Base\EditForm> $providerClass */
	    $providerClass = $arParams['PROVIDER'];

		Market\Reference\Assert::notNull($providerClass, 'arParams[PROVIDER]');
		Market\Reference\Assert::isSubclassOf($providerClass, Market\Component\Base\EditForm::class);

		$this->provider = new $providerClass($this, $arParams);

	    $arParams = $this->provider->prepareComponentParams($arParams);

	    if (empty($arParams['TABS']))
	    {
		    $arParams['TABS'] = [
			    [ 'name' => $this->getLang('DEFAULT_TAB_NAME') ],
		    ];
	    }

		return $arParams;
    }

    public function executeComponent()
    {
        $this->initResult();

        if (!$this->checkParams())
        {
            $this->showErrors();
            return;
        }

        $templatePage = '';
        $isStepsBehavior = ($this->arParams['FORM_BEHAVIOR'] === 'steps');
	    $isAjaxReloader = $this->testAjaxReloaderRequest();

        try
        {
            if ($this->hasCancelRequest())
            {
                $this->redirectCancel();
            }

	        $this->loadItem();
	        $this->buildContextMenu();
	        $this->buildTabs();
	        $this->buildButtons();

	        $requestStep = $this->getRequestStep();
	        $hasRequest = $this->hasRequest();
	        $hasSaveRequest = $this->hasSaveRequest();
	        $isFoundRequestStep = false;
	        $isFirstTab = true;

            foreach ($this->arResult['TABS'] as &$tab)
            {
            	$tabFields = !empty($tab['SELECT']) || $isFirstTab ? $this->loadFields($tab['SELECT']) : [];
            	$stepValidateResult = true;

            	$this->registerTabFields($tab, $tabFields);

            	if ($isAjaxReloader || $hasRequest)
	            {
	                $this->fillRequest($tabFields);
	                $this->resolveDependency($tabFields);

	                if (
	                    $isStepsBehavior
	                    && (
	                        $hasSaveRequest // validate all on save
	                        || (!$isFoundRequestStep && $requestStep !== $tab['STEP']) // validate previous steps on move
                        )
                    )
	                {
		                $stepValidateResult = $this->validateRequest($tabFields);
			        }
		        }
            	else
	            {
	            	$this->resolveDependency($tabFields);
	            }

            	$this->registerFields($tabFields);

		        if ($isStepsBehavior && !$isFoundRequestStep)
	            {
	            	$this->arResult['STEP'] = $tab['STEP'];
	            	$this->arResult['STEP_FINAL'] = $tab['FINAL'];

	                if (!$stepValidateResult || $requestStep === $tab['STEP'])
	                {
	                    $isFoundRequestStep = true;
	                }
		        }

	            $isFirstTab = false;
            }
            unset($tab);

            if (!$isStepsBehavior && $hasSaveRequest)
            {
                $this->validateRequest();
            }

			if ($this->hasAjaxAction())
			{
				$this->processAjaxAction();
			}
			else if ($this->hasPostAction())
			{
				if (!check_bitrix_sessid())
				{
					$this->addError($this->getLang('EXPIRE_SESSION'));
				}

				if (!$this->hasErrors())
				{
					$this->processPostAction();
				}

				if (!$this->hasErrors())
				{
					$this->afterSave($this->getPostAction());
				}
			}
			else if ($hasSaveRequest)
            {
	            $savePrimary = null;

	            if (!$this->arParams['ALLOW_SAVE'])
	            {
		            $this->addError($this->getLang('SAVE_DISALLOW'));
	            }
            	else if (!check_bitrix_sessid())
	            {
	                $this->addError($this->getLang('EXPIRE_SESSION'));
	            }
	            else if (!$this->hasErrors())
	            {
	                $savePrimary = $this->saveFull();
	            }
	            else if ($this->arParams['SAVE_PARTIALLY'])
	            {
	            	$savePrimary = $this->savePartially();
                }

	            if ($savePrimary !== null && !$this->hasErrors())
	            {
		            $this->afterSave('save', $savePrimary);
	            }
            }

            $this->extendItem();
        }
        catch (Main\SystemException $exception)
        {
	        $templatePage = 'exception';
            $this->addError($exception->getMessage());

			$this->arResult['EXCEPTION_MIGRATION'] = Market\Migration\Controller::canRestore($exception);
        }

        $this->setTitle();

	    $this->includeComponentTemplate($templatePage);

		if ($isAjaxReloader)
		{
			$this->sendAjaxReloaderResponse();
		}
    }

    protected function initResult()
    {
        $this->arResult['SUCCESS'] = false;
        $this->arResult['ACTION'] = null;
        $this->arResult['STEP'] = null;
        $this->arResult['STEP_FINAL'] = false;
        $this->arResult['FIELDS'] = [];
        $this->arResult['ITEM'] = [];
        $this->arResult['ITEM_ORIGINAL'] = [];
        $this->arResult['ERRORS'] = [];
        $this->arResult['FIELD_ERRORS'] = [];
        $this->arResult['TABS'] = [];
        $this->arResult['BUTTONS'] = [];
        $this->arResult['HAS_REQUEST'] = false;
		$this->arResult['AJAX_RELOADER_REQUEST'] = false;
    }

    protected function checkParams()
    {
        $result = true;
        $requiredParams = [ 'FORM_ID' ];

        foreach ($requiredParams as $paramKey)
        {
            if (empty($this->arParams[ $paramKey ]))
            {
                $result = false;

                $this->addError($this->getLang('PARAM_REQUIRE', [
                    '#PARAM#' => $paramKey,
                ]));
            }
        }

        return $result;
    }

    protected function addFieldError($fieldName, $message)
    {
        $this->arResult['FIELD_ERRORS'][$fieldName] = true;

        $this->addError($message);
    }

    protected function addError($message)
    {
        $this->arResult['ERRORS'][] = $message;
    }

    public function hasErrors()
    {
        return !empty($this->arResult['ERRORS']);
    }

    public function showErrors()
    {
        \CAdminMessage::ShowMessage([
            'TYPE' => 'ERROR',
            'MESSAGE' => implode('<br />', $this->arResult['ERRORS']),
            'HTML' => true,
        ]);
    }

	public function showMessages()
	{
		if (empty($this->arParams['~MESSAGES'])) { return; }

		foreach ($this->arParams['~MESSAGES'] as $message)
		{
			\CAdminMessage::ShowMessage($message);
		}
	}

    protected function setTitle()
    {
        global $APPLICATION;

        $title = $this->arParams['TITLE'];
        $primary = $this->getPrimary();

        if ($primary === null && $this->arParams['TITLE_ADD'] !== '')
        {
            $title = $this->arParams['TITLE_ADD'];
        }

        if ($title !== '')
        {
            $APPLICATION->SetTitle($title);
        }
    }

    protected function getFieldsSelect()
    {
        $result = [];

        foreach ($this->arParams['TABS'] as $tab)
        {
            if (!empty($tab['fields']))
            {
                foreach ($tab['fields'] as $field)
                {
                    $result[] = $field;
                }
            }
        }

        return $result;
    }

    protected function hasAjaxAction()
    {
        return ($this->getAjaxAction() !== null);
    }

    protected function getAjaxAction()
    {
        return $this->request->getPost('ajaxAction');
    }

    protected function processAjaxAction()
    {
        try
        {
	        $data = $this->arResult['ITEM'];
	        $data['PRIMARY'] = $this->getPrimary();

            $response = $this->provider->processAjaxAction($this->getAjaxAction(), $data);
        }
        catch (Main\SystemException $exception)
        {
            $response = [
                'status' => 'error',
                'message' => $exception->getMessage(),
            ];
        }

		Market\Utils\HttpResponse::sendJson($response);
    }

	protected function hasPostAction()
	{
		return ($this->getPostAction() !== null);
	}

	protected function getPostAction()
	{
		return $this->request->get('postAction');
	}

	protected function processPostAction()
	{
		try
		{
			$data = $this->arResult['ITEM'];
			$data['PRIMARY'] = $this->getPrimary();

			$this->provider->processPostAction($this->getPostAction(), $data);
		}
		catch (Main\SystemException $exception)
		{
			$this->addError($exception->getMessage());
		}
	}

    protected function hasRequest()
    {
        $result = $this->hasStepRequest() || $this->hasSaveRequest() || $this->hasPostAction() || $this->hasAjaxAction();

        $this->arResult['HAS_REQUEST'] = $result;

        return $result;
    }

	protected function testAjaxReloaderRequest()
	{
		$match = ($this->request->isAjaxRequest() && $this->request->getPost('ajaxReloader') === 'Y');

		$this->arResult['AJAX_RELOADER_REQUEST'] = $match;

		if ($match)
		{
			/** @noinspection PhpDeprecationInspection */
			$this->request->addFilter(new Main\Web\PostDecodeFilter());

			$this->arResult['AJAX_RELOADER_TARGET'] = array_flip(explode(
				', ',
				(string)$this->request->getPost('ajaxReloaderTarget')
			));
			$this->arResult['AJAX_RELOADER_CONTENT'] = [];
		}

		return $match;
	}

	protected function sendAjaxReloaderResponse()
	{
		Market\Utils\HttpResponse::sendJson($this->arResult['AJAX_RELOADER_CONTENT']);
		die();
	}

    protected function hasCancelRequest()
    {
        return ($this->request->getPost('cancel') !== null);
    }

    protected function hasStepRequest()
    {
        return ($this->request->getPost('stepAction') !== null);
    }

    protected function hasSaveRequest()
    {
        return ($this->request->getPost('apply') !== null || $this->request->getPost('save') !== null);
    }

    protected function isAjaxForm()
    {
    	return $this->request->getPost('ajaxForm') === 'Y';
    }

    protected function getRequestStep()
    {
        $stepCount = \count($this->arResult['TABS']);
        $stepIndex = (int)$this->request->getPost('STEP');

        // step action

        $stepAction = $this->request->getPost('stepAction');

        switch (true)
        {
	        case ($stepAction === 'previous'):
	            --$stepIndex;
	        break;

	        case ($stepAction === 'next'):
	            ++$stepIndex;
	        break;

	        case (is_numeric($stepAction)):
                $stepIndex = (int)$stepAction;
	        break;
        }

        // normalize index

        if ($stepIndex <= 0)
        {
            $stepIndex = 0;
        }
        else if ($stepIndex >= $stepCount)
        {
            $stepIndex = $stepCount - 1;
        }

        return $stepIndex;
    }

    protected function fillRequest($fields)
    {
		$files = $this->request->getFileList()->toArray();
		$post = $this->request->getPostList()->toArray();

        foreach ($fields as $field)
        {
            if ($field['USER_TYPE']['BASE_TYPE'] === 'file')
            {
	            $this->getFileByRequestKey($post, $files, $field['FIELD_NAME'], $this->arResult['ITEM']);
            }
            else
            {
	            $this->getValueByRequestKey($post, $field['FIELD_NAME'], $this->arResult['ITEM']);
            }
        }

        $this->arResult['ITEM'] = $this->provider->modifyRequest($this->arResult['ITEM'], $fields);
    }

    protected function getValueByRequestKey($values, $key, &$result)
    {
        $keyChain = $this->splitFieldNameToChain($key);
        $value = $this->getValueByChain($values, $keyChain);

        $this->setValueByChain($result, $keyChain, $value);
    }

    protected function getFileByRequestKey($post, $files, $key, &$result)
    {
	    $keyChain = $this->splitFieldNameToChain($key);

	    if (count($keyChain) > 1)
	    {
		    $files = $this->normalizeFileUploadChain($files, $keyChain);
	    }

		$firstKey = reset($keyChain);
        $lastKey = end($keyChain);
	    $deleteRequest = $this->getValueByChain($post, [ $firstKey . '_del' ] + $keyChain);
		$oldIdRequest = $this->getValueByChain($post, [ $firstKey . '_old_id' ] + $keyChain);

        $request = isset($files[$lastKey]) && is_array($files[$lastKey]) ? $files[$lastKey] : [];

        if ($deleteRequest !== null)
	    {
		    $request['del'] = ($deleteRequest === 'Y');
	    }

        if ($oldIdRequest !== null)
	    {
		    $request['old_id'] = (int)$oldIdRequest;
	    }

	    if (
			(!isset($request['error']) || $request['error'] === UPLOAD_ERR_NO_FILE)
	        && empty($request['del'])
	    )
	    {
		    $request = isset($request['old_id']) ? $request['old_id'] : null;
	    }

	    $this->setValueByChain($result, $keyChain, $request);
    }

	protected function normalizeFileUploadChain($files, array $keyChain)
	{
		array_pop($keyChain);
		$files = $this->getValueByChain($files, $keyChain);

		if (!is_array($files)) { return []; }

		$result = [];

		foreach ($files as $name => $values)
		{
			if (!is_array($values)) { continue; }

			foreach ($values as $key => $value)
			{
				if (!isset($result[$key])) { $result[$key] = []; }

				$result[$key][$name] = $value;
			}
		}

		return $result;
	}

	protected function resolveDependency(&$fields)
	{
		$statuses = $this->getDependencyStatuses($fields);

		foreach ($statuses as $fieldName => $status)
		{
			if (!isset($fields[$fieldName])) { continue; }

			$fields[$fieldName]['DEPEND_HIDDEN'] = $status;
		}
	}

	protected function getDependencyStatuses($fields)
	{
		$result = [];

		foreach ($fields as $fieldName => $field)
		{
			if (!isset($field['DEPEND'])) { continue; }

			$result[$fieldName] = !Market\Utils\UserField\DependField::test($field['DEPEND'], $this->arResult['ITEM']);
		}

		return $result;
	}

    protected function validateRequest($fields = null)
    {
    	if ($fields === null)
	    {
	    	$fields = $this->arResult['FIELDS'];
	    }

    	$data = $this->arResult['ITEM'];
    	$data['PRIMARY'] = $this->getPrimary();

        $validationResult = $this->provider->validate($data, $fields);
        $result = false;

        if ($validationResult->isSuccess())
        {
            $result = true;
        }
        else
        {
            $errors = $validationResult->getErrors();

            if (!empty($errors))
            {
                foreach ($errors as $error)
                {
                    $errorCustomData = method_exists($error, 'getCustomData') ? $error->getCustomData() : null;

                    if (isset($errorCustomData['FIELD']))
                    {
                    	$this->addFieldError($errorCustomData['FIELD'], $error->getMessage());
                    }
                    else
                    {
                        $this->addError($error->getMessage());
                    }
                }
            }
            else
            {
                $this->addError($this->getLang('VALIDATE_ERROR_UNDEFINED'));
            }
        }

        return $result;
    }

    protected function saveFull()
    {
	    $fields = $this->arResult['ITEM'];

    	return $this->save($fields);
    }

    protected function savePartially()
    {
	    $fields = $this->arResult['ITEM'];
	    $fieldsOriginal = $this->arResult['ITEM_ORIGINAL'];

	    foreach ($this->getFieldsWithError() as $fieldName)
	    {
			if (!array_key_exists($fieldName, $fields))
			{
				// nothing
			}
			else if (array_key_exists($fieldName, $fieldsOriginal))
			{
				$fields[$fieldName] = $fieldsOriginal[$fieldName];
			}
			else
			{
				unset($fields[$fieldName]);
			}
	    }

	    if (!empty($fields))
	    {
	    	$result = $this->save($fields);
	    }
	    else
	    {
	    	$result = null;
	    }

    	return $result;
    }

    protected function save($fields)
    {
        $primary = $this->getPrimary();
        $result = null;

        if ($primary !== null)
        {
            $saveResult = $this->provider->update($primary, $fields);

			if ($saveResult->isSuccess())
			{
				$updatePrimary = $saveResult->getPrimary();

				if ($updatePrimary)
				{
					$primary = is_array($updatePrimary) && count($updatePrimary) === 1
						? end($updatePrimary)
						: $updatePrimary;
				}
			}
        }
        else
        {
            $saveResult = $this->provider->add($fields);

            if ($saveResult->isSuccess())
            {
                $primary = $saveResult->getId();
            }
        }

        if ($saveResult->isSuccess())
        {
			$result = is_array($primary) ? end($primary) : $primary;
        }
        else
        {
            $errors = $saveResult->getErrors();

            if (!empty($errors))
            {
                foreach ($errors as $error)
                {
                    $this->addError($error->getMessage());
                }
            }
            else
            {
                $this->addError($this->getLang('SAVE_ERROR_UNDEFINED'));
            }
        }

        return $result;
    }

    protected function afterSave($action, $primary = null)
    {
    	if ($this->isAjaxForm())
	    {
			$this->arResult['PRIMARY'] = $primary !== null ? $primary : $this->getPrimary();
			$this->arResult['ACTION'] = $action;
			$this->arResult['SUCCESS'] = true;
	    }
    	else
	    {
	    	$this->redirectAfterSave($primary);
	    }
    }

    protected function getPrimary($useOrigin = false)
    {
	    $result = null;

	    if (
	    	!empty($this->arParams['PRIMARY'])
	        && (!$this->arParams['COPY'] || $useOrigin)
	    )
	    {
		    $result = $this->arParams['PRIMARY'];
	    }

    	return $result;
    }

    protected function redirectCancel()
    {
        LocalRedirect($this->arParams['LIST_URL']);
    }

    protected function redirectAfterSave($primary = null)
    {
        global $APPLICATION;

        $redirectUrl = (string)($this->arParams['REDIRECT_URL'] ?: '');
	    $parameters = [];

	    if ($primary !== null)
	    {
		    $parameters['id'] = is_array($primary) ? reset($primary) : $primary;
	    }

	    if ($this->arParams['FORM_BEHAVIOR'] !== 'steps')
	    {
		    $activeTabRequestKey = $this->arParams['FORM_ID'] . '_active_tab';
		    $activeTab = $this->request->getPost($activeTabRequestKey);

		    $parameters[$activeTabRequestKey] = $activeTab;
	    }

	    if ($this->request->getPost('save'))
	    {
		    $redirectUrl = (string)($this->arParams['SAVE_URL'] ?: $this->arParams['LIST_URL']);
	    }

        if ($redirectUrl !== '')
        {
	        $redirectUrlCurrentPagePosition = Market\Data\TextString::getPositionCaseInsensitive(
		        $redirectUrl,
		        $APPLICATION->GetCurPage(false)
	        );
	        $isSamePage = ($redirectUrlCurrentPagePosition === 0);
        	$leftParameters = [];

        	foreach ($parameters as $name => $value)
	        {
	        	$searchHolder = '#' . Market\Data\TextString::toUpper($name) . '#';
	        	$searchPosition = Market\Data\TextString::getPosition($redirectUrl, $searchHolder);

	        	if ($searchPosition !== false)
		        {
		        	$redirectUrl = str_replace($searchHolder, $value, $redirectUrl);
		        }
	        	else
		        {
		        	$leftParameters[$name] = $value;
		        }
	        }

        	if (!empty($leftParameters) && $isSamePage)
	        {
	        	$redirectUrl .=
			        (Market\Data\TextString::getPosition($redirectUrl, '?') === false ? '?' : '&')
			        . http_build_query($leftParameters);
	        }
        }
        else
        {
	        $redirectUrl = $APPLICATION->GetCurPageParam(
	        	http_build_query($parameters),
		        array_keys($parameters)
	        );
        }

        LocalRedirect($redirectUrl);
    }

    protected function loadItem()
    {
        $primary = $this->getPrimary(true);
	    $fieldsSelect = $this->getFieldsSelect();

        if ($primary !== null)
        {
            $this->arResult['ITEM'] = $this->provider->load($primary, $fieldsSelect, $this->arParams['COPY']);
        }
		else
		{
			$this->arResult['ITEM'] = $this->provider->initial($fieldsSelect);
		}

	    $this->arResult['ITEM_ORIGINAL'] = $this->arResult['ITEM'];
    }

    protected function loadFields($select)
    {
	    return $this->provider->getFields((array)$select, $this->arResult['ITEM']);
    }

    protected function registerFields($fields)
    {
    	$this->arResult['FIELDS'] += $fields;
    }

    protected function extendItem()
    {
		if ($this->arParams['FORM_BEHAVIOR'] === 'steps')
		{
			foreach ($this->arResult['TABS'] as $tab)
			{
				if ($tab['STEP'] !== $this->arResult['STEP']) { continue; }

				$this->arResult['ITEM'] = $this->provider->extend(
					$this->arResult['ITEM'],
					array_intersect_key($this->arResult['FIELDS'], array_flip($tab['FIELDS']))
				);
				break;
			}

			return;
		}

        $this->arResult['ITEM'] = $this->provider->extend($this->arResult['ITEM'], $this->arResult['FIELDS']);
    }

    protected function buildContextMenu()
    {
		$this->arResult['CONTEXT_MENU'] = $this->arParams['CONTEXT_MENU']; // simple copy, need for future modifications
    }

    protected function buildTabs()
    {
        $paramTabs = $this->arParams['TABS'];
        $countTabs = count($paramTabs);
        $hasFinalTab = false;
        $tabIndex = 0;
        $result = [];

        foreach ($paramTabs as $paramTab)
        {
            $isFinalTab = (!empty($paramTab['final']) || (!$hasFinalTab && $tabIndex === $countTabs - 1));

            if ($isFinalTab)
            {
                $hasFinalTab = true;
            }

            $result[] = [
                'STEP' => $tabIndex,
                'FINAL' => $isFinalTab,
                'DIV' => isset($paramTab['id']) ? 'tab_' . $paramTab['id'] : 'tab' . $tabIndex,
                'TAB' => $paramTab['name'],
                'LAYOUT' => $paramTab['layout'] ?: 'default',
                'SELECT' => $paramTab['fields'] ?: [],
                'FIELDS' => [],
                'HIDDEN' => [],
                'DATA' => isset($paramTab['data']) ? (array)$paramTab['data'] : [],
            ];

            $tabIndex++;
        }

        $this->arResult['TABS'] = $result;
    }

    protected function buildButtons()
    {
    	if (!empty($this->arParams['BUTTONS']))
	    {
	        $this->arResult['BUTTONS'] = (array)$this->arParams['BUTTONS'];
	    }
		else if ($this->arParams['FORM_BEHAVIOR'] === 'steps')
		{
			$this->arResult['BUTTONS'] = [
				[ 'BEHAVIOR' => 'previous' ],
				[ 'BEHAVIOR' => 'next' ],
			];
		}
		else
		{
			$this->arResult['BUTTONS'] = [
				[ 'BEHAVIOR' => 'save' ],
				[ 'BEHAVIOR' => 'apply' ],
			];
		}
    }

    protected function registerTabFields(&$tab, $fields)
    {
        foreach ($fields as $fieldKey => $field)
        {
	        if (!empty($field['HIDDEN']) && $field['HIDDEN'] !== 'N')
	        {
	            $tab['HIDDEN'][] = $fieldKey;
	        }
	        else
	        {
	            $tab['FIELDS'][] = $fieldKey;
	        }
        }
    }

	public function getField($fieldKey)
	{
		$result = null;

        if (isset($this->arResult['FIELDS'][$fieldKey]))
        {
            $result = $this->arResult['FIELDS'][$fieldKey];
        }

        return $result;
	}

    public function hasFieldError($field)
    {
        return !empty($this->arResult['FIELD_ERRORS'][$field['FIELD_NAME']]);
    }

    public function getFieldsWithError()
    {
    	return array_keys($this->arResult['FIELD_ERRORS']);
    }

    public function getFieldTitle($field)
    {
        return $this->getFirstNotEmpty(
            $field,
            [ 'EDIT_FORM_LABEL', 'LIST_COLUMN_LABEL', 'LIST_FILTER_LABEL' ]
        );
    }

    public function getFieldValue($field)
    {
        // try fetch from item

        $keyChain = $this->splitFieldNameToChain($field['FIELD_NAME']);
        $result = $this->getValueByChain($this->arResult['ITEM'], $keyChain);

		// may be defined value

		if ($result !== null)
		{
			// nothing
		}
		else if (isset($field['VALUE']))
        {
            $result = $field['VALUE'];
        }
        else if (isset($field['SETTINGS']['DEFAULT_VALUE']))
        {
			$result = $field['SETTINGS']['DEFAULT_VALUE'];
        }

        return $result;
    }

    public function normalizeFieldValue($field, $value)
    {
    	if ($field['MULTIPLE'] !== 'N' && is_scalar($value) && (string)$value !== '')
	    {
			$result = [ $value ];
	    }
    	else
	    {
	    	$result = $value;
	    }

    	return Market\Ui\UserField\Helper\Field::unifyValue($result);
    }

    public function getOriginalValue($field)
    {
	    $keyChain = $this->splitFieldNameToChain($field['FIELD_NAME']);

	    return $this->getValueByChain($this->arResult['ITEM_ORIGINAL'], $keyChain);
    }

    public function getFieldHtml($field, $value = null, $isExtended = false)
    {
        global $USER_FIELD_MANAGER;

        $result = null;

        if (empty($field['HIDDEN']) || $field['HIDDEN'] === 'N')
        {
            $field['ENTITY_VALUE_ID'] = $this->getPrimary();
            $field['VALUE'] = $value !== null ? $value : $this->getFieldValue($field);
            $field['VALUE'] = $this->normalizeFieldValue($field, $field['VALUE']);
	        $field['ROW'] = $this->arResult['ITEM'];

	        $html = $USER_FIELD_MANAGER->GetEditFormHTML(false, null, $field);

	        $parsedHtml = Market\Ui\UserField\Helper\Renderer::parseEditHtml($html);

	        $result = $isExtended ? $parsedHtml : $parsedHtml['CONTROL'];
        }

        return $result;
    }

    protected function getFirstNotEmpty($data, $keys)
    {
        $result = null;

        foreach ($keys as $key)
        {
            if (!empty($data[ $key ]))
            {
                $result = $data[ $key ];
                break;
            }
        }

        return $result;
    }

    public function getLang($code, $replaces = null)
    {
		return Main\Localization\Loc::getMessage(self::LANG_PREFIX . $code, $replaces) ?: $code;
    }

    protected function getValueByChain($item, $keyChain)
    {
	    return Market\Utils\Field::getChainValue($item, $keyChain, Market\Utils\Field::GLUE_BRACKET);
    }

    protected function setValueByChain(&$item, $keyChain, $value)
    {
	    Market\Utils\Field::setChainValue($item, $keyChain, $value, Market\Utils\Field::GLUE_BRACKET);
    }

    protected function splitFieldNameToChain($key)
    {
        return Market\Utils\Field::splitKey($key, Market\Utils\Field::GLUE_BRACKET);
    }
}