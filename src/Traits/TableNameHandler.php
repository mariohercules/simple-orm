<?php

namespace SimpleORM\Traits;

trait TableNameHandler
{
    protected function getTableName(): string
    {
        if (isset($this->table)) {
            return $this->table;
        }
        
        return strtolower(preg_replace(
            '/(?<!^)[A-Z]/',
            '_$0',
            basename(str_replace('\\', '/', get_class($this)))
        ));
    }
}