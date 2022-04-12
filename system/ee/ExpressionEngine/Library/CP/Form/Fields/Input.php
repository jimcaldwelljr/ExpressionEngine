<?php

namespace EeObjects\Forms\Form\Fields;

class Input extends Html
{
    /**
     * @var array
     */
    protected $custom_params = [];

    /**
     * @return array
     */
    public function toArray(): array
    {
        $defaults = [
            'type' => $this->get('type'),
            'name' => $this->getName(),
            'value' => set_value($this->getName(), $this->getValue())
        ];

        if($this->custom_params) {
            $defaults = array_merge($defaults, $this->custom_params);
        }

        $input = "<input " . _parse_form_attributes($this->getName(), $defaults) . " />";
        $this->setContent($input);
        $this->set('type', 'html');
        return parent::toArray();
    }

    /**
     * @param array $params
     * @return $this
     */
    public function params(array $params = [])
    {
        $this->custom_params = $params;
        return $this;
    }
}