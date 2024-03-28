<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

$this->setFrameMode(true);
?>

<?php if (!empty($arResult)): ?>
    <ol id="iblock-section-tree">
        <?php foreach ($arResult as $arSection): ?>
            <li class="section-level-<?php echo $arSection['DEPTH_LEVEL']; ?>">
                <?php echo $arSection['NAME']; ?>
                <?php if($arSection['ITEMS']) { ?>
                    <ul>
                        <?php foreach($arSection['ITEMS'] as $item) { ?>
                            <li><?php echo $item['NAME'] . (count($item['NEW_TAGS']) > 1 ? ', (' . implode(', ', $item['NEW_TAGS']) . ')' : ''); ?></li>
                        <?php } ?>
                    </ul>
                <?php } ?>
            </li>
        <?php endforeach; ?>
    </ol>
<?php endif; ?>