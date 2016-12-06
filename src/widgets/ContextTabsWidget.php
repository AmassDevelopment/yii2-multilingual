<?php

namespace DevGroup\Multilingual\widgets;

use DevGroup\Multilingual\interfaces\ContentTabHandlerInterface;
use DevGroup\Multilingual\models\Context;
use yii\base\Widget;
use yii\helpers\ArrayHelper;

class ContextTabsWidget extends Widget
{
    public $wrapperViewFile = 'context_tabs';
    public $tabViewFile = 'context_tab';
    public $options = [];
    /**
     * @var ContentTabHandlerInterface[]
     */
    public $handlers = [];

    public function run()
    {
        $contexts = ArrayHelper::merge(Context::getListData(), ['common' => 'common']);
        $result = [];
        foreach ($contexts as $id => $label) {
            $tmp = [];
            foreach ($this->handlers as $handler) {
                $tmp = ArrayHelper::merge($tmp, $handler->contextData($id));
            }
            $result[] = [
                'label' => $label,
                'content' => $this->render($this->tabViewFile, ['data' => $tmp, 'context_id' => $id]),
            ];
        }

        return $this->render($this->wrapperViewFile, ['tabs' => $result, 'options' => $this->options]);
    }
}
