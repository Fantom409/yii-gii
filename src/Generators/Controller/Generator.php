<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace Yiisoft\Yii\Gii\Generators\Controller;

use Yiisoft\Strings\Inflector;
use Yiisoft\Strings\StringHelper;
use Yiisoft\Yii\Gii\CodeFile;

/**
 * This generator will generate a controller and one or a few action view files.
 *
 * @property array $actionIDs An array of action IDs entered by the user. This property is read-only.
 * @property string $controllerFile The controller class file path. This property is read-only.
 * @property string $controllerID The controller ID. This property is read-only.
 * @property string $controllerNamespace The namespace of the controller class. This property is read-only.
 * @property string $controllerSubPath The controller sub path. This property is read-only.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class Generator extends \Yiisoft\Yii\Gii\Generators\Generator
{
    /**
     * @var string the controller class name
     */
    public $controllerClass;
    /**
     * @var string the controller's view path
     */
    public $viewPath;
    /**
     * @var string the base class of the controller
     */
    public $baseClass;
    /**
     * @var string list of action IDs separated by commas or spaces
     */
    public $actions = 'index';


    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'Controller Generator';
    }

    /**
     * {@inheritdoc}
     */
    public function getDescription(): string
    {
        return 'This generator helps you to quickly generate a new controller class with
            one or several controller actions and their corresponding views.';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return array_merge(
            parent::rules(),
            [
                [['controllerClass', 'actions', 'baseClass'], 'filter', 'filter' => 'trim'],
                [['controllerClass', 'baseClass'], 'required'],
                [
                    'controllerClass',
                    'match',
                    'pattern' => '/^[\w\\\\]*Controller$/',
                    'message' => 'Only word characters and backslashes are allowed, and the class name must end with "Controller".'
                ],
                ['controllerClass', 'validateNewClass'],
                [
                    'baseClass',
                    'match',
                    'pattern' => '/^[\w\\\\]*$/',
                    'message' => 'Only word characters and backslashes are allowed.'
                ],
                [
                    'actions',
                    'match',
                    'pattern' => '/^[a-z][a-z0-9\\-,\\s]*$/',
                    'message' => 'Only a-z, 0-9, dashes (-), spaces and commas are allowed.'
                ],
                ['viewPath', 'safe'],
            ]
        );
    }

    public function attributeLabels(): array
    {
        return [
            'baseClass' => 'Base Class',
            'controllerClass' => 'Controller Class',
            'viewPath' => 'View Path',
            'actions' => 'Action IDs',
        ];
    }

    public function requiredTemplates()
    {
        return [
            'controller.php',
            'view.php',
        ];
    }

    public function stickyAttributes()
    {
        return ['baseClass'];
    }

    public function hints()
    {
        return [
            'controllerClass' => 'This is the name of the controller class to be generated. You should
                provide a fully qualified namespaced class (e.g. <code>app\controllers\PostController</code>),
                and class name should be in CamelCase ending with the word <code>Controller</code>. Make sure the class
                is using the same namespace as specified by your application\'s controllerNamespace property.',
            'actions' => 'Provide one or multiple action IDs to generate empty action method(s) in the controller. Separate multiple action IDs with commas or spaces.
                Action IDs should be in lower case. For example:
                <ul>
                    <li><code>index</code> generates <code>actionIndex()</code></li>
                    <li><code>create-order</code> generates <code>actionCreateOrder()</code></li>
                </ul>',
            'viewPath' => 'Specify the directory for storing the view scripts for the controller. You may use path alias here, e.g.,
                <code>/var/www/basic/controllers/views/order</code>, <code>@app/views/order</code>. If not set, it will default
                to <code>@app/views/ControllerID</code>',
            'baseClass' => 'This is the class that the new controller class will extend from. Please make sure the class exists and can be autoloaded.',
        ];
    }

    public function successMessage()
    {
        return 'The controller has been generated successfully.' . $this->getLinkToTry();
    }

    /**
     * This method returns a link to try controller generated
     * @return string
     */
    private function getLinkToTry()
    {
        return '';
    }

    public function generate(): array
    {
        $files = [];

        $files[] = new CodeFile(
            $this->getControllerFile(),
            $this->render('controller.php')
        );

        foreach ($this->getActionIDs() as $action) {
            $files[] = new CodeFile(
                $this->getViewFile($action),
                $this->render('view.php', ['action' => $action])
            );
        }

        return $files;
    }

    /**
     * Normalizes [[actions]] into an array of action IDs.
     * @return array an array of action IDs entered by the user
     */
    public function getActionIDs()
    {
        $actions = array_unique(preg_split('/[\s,]+/', $this->actions, -1, PREG_SPLIT_NO_EMPTY));
        sort($actions);

        return $actions;
    }

    /**
     * @return string the controller class file path
     */
    public function getControllerFile()
    {
        return $this->aliases->get('@' . str_replace('\\', '/', $this->controllerClass)) . '.php';
    }

    /**
     * @return string the controller ID
     */
    public function getControllerID()
    {
        $name = StringHelper::basename($this->controllerClass);
        return (new Inflector())->camel2id(substr($name, 0, strlen($name) - 10));
    }

    /**
     * This method will return sub path for controller if it
     * is located in subdirectory of application controllers dir
     * @see https://github.com/yiisoft/yii2-gii/issues/182
     * @since 2.0.6
     * @return string the controller sub path
     */
    public function getControllerSubPath()
    {
        $subPath = '';
        $controllerNamespace = $this->getControllerNamespace();
        if (strpos($controllerNamespace, Yii::getApp()->controllerNamespace) === 0) {
            $subPath = substr($controllerNamespace, strlen(Yii::getApp()->controllerNamespace));
            $subPath = ($subPath !== '') ? str_replace('\\', '/', substr($subPath, 1)) . '/' : '';
        }
        return $subPath;
    }

    /**
     * @param string $action the action ID
     * @return string the action view file path
     */
    public function getViewFile($action)
    {
        if (empty($this->viewPath)) {
            return $this->aliases->get(
                '@app/views/' . $this->getControllerSubPath() . $this->getControllerID() . "/$action.php"
            );
        }

        return $this->aliases->get(str_replace('\\', '/', $this->viewPath) . "/$action.php");
    }

    /**
     * @return string the namespace of the controller class
     */
    public function getControllerNamespace()
    {
        $name = StringHelper::basename($this->controllerClass);
        return ltrim(substr($this->controllerClass, 0, -(strlen($name) + 1)), '\\');
    }
}
