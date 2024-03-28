<?
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

// класс для работы с языковыми файлами
use Bitrix\Main\Localization\Loc;
// класс для всех исключений в системе
use Bitrix\Main\SystemException;
// класс для загрузки необходимых файлов, классов, модулей
use Bitrix\Main\Loader;
use Bitrix\Iblock\ElementTable;
use Bitrix\Iblock\SectionTable;
use Bitrix\Main\Entity\ExpressionField;
use Bitrix\Iblock\Component\Tools;

// основной класс, является оболочкой компонента унаследованного от CBitrixComponent
class SectItemsList extends CBitrixComponent
{

    // выполняет основной код компонента, аналог конструктора (метод подключается автоматически)
    public function executeComponent()
    {
        try {
            // подключаем метод проверки подключения модуля «Информационные блоки»
            $this->checkModules();
            // подключаем метод подготовки массива $arResult
            $this->getResult();
        } catch (SystemException $e) {
            ShowError($e->getMessage());
        }
    }

    // подключение языковых файлов (метод подключается автоматически)
    public function onIncludeComponentLang()
    {
        Loc::loadMessages(__FILE__);
    }

    // проверяем установку модуля «Информационные блоки» (метод подключается внутри класса try...catch)
    protected function checkModules()
    {
        // если модуль не подключен
        if (!Loader::includeModule('iblock'))
            // выводим сообщение в catch
            throw new SystemException(Loc::getMessage('IBLOCK_MODULE_NOT_INSTALLED'));
    }

    // обработка массива $arParams (метод подключается автоматически)
    public function onPrepareComponentParams($arParams)
    {
        // время кеширования
        if (!isset($arParams['CACHE_TIME'])) {
            $arParams['CACHE_TIME'] = 3600;
        } else {
            $arParams['CACHE_TIME'] = intval($arParams['CACHE_TIME']);
        }
        $arParams['IBLOCK_ID'] = intval($arParams['IBLOCK_ID']);
        // возвращаем в метод новый массив $arParams     
        return $arParams;
    }

    // подготовка массива $arResult (метод подключается внутри класса try...catch)
    protected function getResult()
    {

        // если нет валидного кеша, получаем данные из БД
        if ($this->startResultCache()) {
            
            Loader::includeModule('iblock');
            
            $runtime = [
                'elements' => [
                    'data_type' =>'Bitrix\Iblock\ElementTable',
                    'expression' => 'COUNT(*)',
                    'reference' => [
                        '=this.IBLOCK_ID' => 'ref.IBLOCK_ID',
                        '=this.ID' => 'ref.IBLOCK_SECTION_ID',
                        '=this.ACTIVE' => 'ref.ACTIVE',
                    ],
                    //'join_type' => 'LEFT'
                ],
                new ExpressionField('ELEMENTS_COUNT', 'COUNT(*)')
            ];

            $res = SectionTable::getList(array(
                'filter' => array(
                    'IBLOCK_ID' => $this->arParams['IBLOCK_ID'],
                    'DEPTH_LEVEL' => 1,
                    'ACTIVE' => 'Y',
                ), 
                'select' => array('ID', 'CODE', 'NAME', 'DEPTH_LEVEL', 'SECTION_PAGE_URL' => 'IBLOCK.SECTION_PAGE_URL', 'ELEMENTS_COUNT'),
                'order' => array('SORT' => 'ASC'),
                'runtime' => $runtime
            ));

            while ($arItem = $res->fetch())
            {
                $this->arResult[$arItem['ID']] = $arItem;
                $this->arResult[$arItem['ID']]['ITEMS'] = array();
            }

            $items = ElementTable::getList([
                'order' => ['SORT' => 'ASC'],
                'select' => ['ID', 'NAME', 'IBLOCK_ID', 'IBLOCK_SECTION_ID'],
                'filter' => ['=ACTIVE' => 'Y', 'IBLOCK_SECTION_ID' => array_keys($this->arResult)],
            ])->fetchAll();
            
            foreach ($items as $item)
            {
                $dbProperty = \CIBlockElement::getProperty($item['IBLOCK_ID'], $item['ID'], array('sort', 'asc'), array('CODE' => 'NEW_TAGS'));
                while ($arProperty = $dbProperty->GetNext())
                {
                    $item['NEW_TAGS'][] = $arProperty['VALUE'];
                }                

                $this->arResult[$item['IBLOCK_SECTION_ID']]['ITEMS'][] = $item;
            }

            // кэш не затронет весь код ниже, он будут выполняться на каждом хите, здесь работаем с другим $arResult, будут доступны только те ключи массива, которые перечислены в вызове SetResultCacheKeys()
            if (isset($this->arResult)) {
                // ключи $arResult перечисленные при вызове этого метода, будут доступны в component_epilog.php и ниже по коду, обратите внимание там будет другой $arResult
                $this->SetResultCacheKeys(
                    array('IBLOCK_TYPE', 'IBLOCK_ID')
                );
                // подключаем шаблон и сохраняем кеш
                $this->IncludeComponentTemplate();
            } else { // если выяснилось что кешировать данные не требуется, прерываем кеширование и выдаем сообщение «Страница не найдена»
                $this->AbortResultCache();
                Tools::process404(
                    Loc::getMessage('PAGE_NOT_FOUND'),
                    true,
                    true
                );
            }
        }
    }
}
