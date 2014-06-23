<?php
App::uses('Component', 'Controller');

class DataTablesComponent extends Component {

    private $model;
    private $controller;
    
    public function initialize(Controller $controller){
        $this->controller = $controller;
        $modelName = $this->controller->modelClass;
        $this->model = $this->controller->{$modelName};
    }     
}
