<?php

namespace markhuot\craftai\behaviors;

use Craft;
use craft\web\Request;
use craft\web\Response;
use markhuot\craftai\db\ActiveRecord;
use yii\base\Behavior;
use yii\base\Model;
use yii\web\BadRequestHttpException;

/**
 * @property Request $owner;
 */
class BodyParamObjectBehavior extends Behavior
{
    /**
     * @template T
     *
     * @param  class-string<T>  $class
     * @return T
     */
    public function getBodyParamObject(string|object $class, string $formName = '')
    {
        if (! $this->owner->getIsPost()) {
            throw new BadRequestHttpException('Post request required');
        }

        $bodyParams = $this->owner->getBodyParams();

        if (is_subclass_of($class, ActiveRecord::class)) {
            $key = $bodyParams[$class::$keyField] ?? null;
            if ($key) {
                $model = $class::find()->where([$class::$keyField => $key])->one();
            }

            if (empty($model)) {
                $model = $class::make(array_filter([
                    $class::$polymorphicKeyField => $bodyParams[$class::$polymorphicKeyField] ?? null,
                ]));
            }
        } else {
            $model = new $class;
        }

        $model->load($bodyParams, $formName);

        if (! $model->validate()) {
            $this->owner->getAcceptsJson() ?
                $this->errorJson($model) :
                $this->errorBack($model);
        }

        return $model;
    }

    public function errorJson(Model $model)
    {
        $response = new Response();
        $response->setStatusCode(500);
        $response->headers->add('content-type', 'application/json');
        $response->content = json_encode([
            'errors' => $model->errors,
        ], JSON_THROW_ON_ERROR);
        Craft::$app->end(500, $response);
    }

    public function errorBack(Model $model)
    {
        foreach ($model->errors as $key => $messages) {
            Craft::$app->session->setFlash('error.'.$key, implode(',', $messages));
        }

        $this->setOldFlashes(Craft::$app->request->getBodyParams());

        $response = new Response();
        $response->setStatusCode(302);
        $response->headers->add('Location', Craft::$app->request->getUrl());
        Craft::$app->end(500, $response);
    }

    protected function setOldFlashes($array, $prefix = '')
    {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $this->setOldFlashes($value, implode('.', array_filter([$prefix, $key])));
            } else {
                Craft::$app->session->setFlash('old.'.implode('.', array_filter([$prefix, $key])), $value);
            }
        }
    }
}
