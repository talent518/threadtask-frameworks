<?php
namespace fwe\db;

/**
 * 数据库模块基类
 */
abstract class Model extends \fwe\base\Model {
    public $isNewRecord = true;
    protected $attributes = [];

    abstract protected function getAttributeNames();

    protected $attributeNames;
    public function hasAttribute(string $name) {
        if($this->attributeNames === null) {
            $this->attributeNames = [];
            
            foreach($this->getAttributeNames() as $key => $attr) {
                if(is_string($key)) {
                    $this->attributeNames[$key] = 1;
                } else {
                    $this->attributeNames[$attr] = 1;
                }
            }
        }

        return isset($this->attributeNames[$name]);
	}

	public function getAttribute(string $name, $default = null) {
        return $this->attributes[$name] ?? $default;
	}

	public function setAttribute(string $name, $value) {
        $this->attributes[$name] = $value;
	}

    public function getAttributes(bool $isAll = false) {
        if ($isAll) {
            return parent::getAttributes() + $this->attributes;
        } else {
            return $this->attributes;
        }
    }

    protected function hasProperty(string $name) {
		return $this->hasAttribute($name);
	}

	protected function getProperty(string $name) {
        return $this->getAttribute($name);
	}

	protected function setProperty(string $name, $value) {
        $this->setAttribute($name, $value);
	}

	protected function delProperty(string $name) {
        $this->setAttribute($name, null);
	}

}