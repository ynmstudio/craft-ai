<?php

namespace markhuot\craftai\models;

use craft\base\Model;

class TextEditResponse extends Model
{
    public ?string $text = '';

    public function rules(): array
    {
        return [
            ['text', 'required'],
        ];
    }
}
