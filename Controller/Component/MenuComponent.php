<?php
/**
 * 
 */
class MenuComponent extends Component
{
	/**
	 * Referência para o controlador que está em execução
	 * 
	 * @var Controller
	 */
	protected $Controller = null;
	
	/**
	 * Componentes utilizados
	 * @var array
	 */
	public $components = array('Auth', 'Acl', 'Session');
	
	protected  $_user = null;
	
	private $_defaultSettings = array(
		'acoTree' => array()
	);
	
	/**
	 * Construtor padrão
	 * 
	 * @param ComponentCollection $collection
	 * @param array $settings
	 */
	public function __construct(ComponentCollection $collection, $settings = array())
	{
		parent::__construct($collection, $settings);
		
		$this->settings = Set::merge($this->_defaultSettings, $settings);
	}
	
	/**
	 * Inicialização do componente
	 * 
	 * @param Controller $controller
	 * @param array $settings
	 */
	public function initialize(&$controller)
	{
		$this->Controller =& $controller;
		
		$this->_user = $this->Auth->user();
	}
	
	/**
	 * Callback invocado imediatamente antes do Controller::beforeFilter()
	 * 
	 * @param Controller $controller
	 */
	public function startup(&$controller)
	{
		$this->Controller =& $controller;
	}
	
	/**
	 * Redefine árvore de Acos
	 * 
	 * @param array $acos
	 */
	public function setAcoTree(&$acos)
	{
		$this->settings['acoTree'] =& $acos;
	}
	
	/**
	 * Constroí menu, a partir de acos e lista de items
	 * 
	 * @param array $items
	 * @param string $cacheKey
	 */
	public function build($items = array(), $cacheKey = 'Menu')
	{
		$menu = array();
		$indexes = array();
		$node = '';
		
		if(empty($this->settings['acoTree']))
			return array();
			
		if(empty($items))
		{
			$_Menu =& ClassRegistry::init('Menu', 'Model');
			
			$items = $_Menu->find('all');
		}
			
		foreach($items as $key => $item)
		{
			$m = $item['Menu'];
			
			// se não houver permissão para acessar ação, passa para próximo item
			if(!$this->checkMenuNode($m))
				continue;
			
			if(empty($m['parent_id']))
			{
				$menu[$m['id']] = $m;
				
				$indexes[$m['id']] = &$menu[$m['id']];
			}
			// verifica se o nó pai já foi preenchido
			else if(!empty($indexes[$m['parent_id']]))
			{
				$indexes[$m['parent_id']]['childs'][$key] = $m;
				
				$indexes[$m['id']] = &$indexes[$m['parent_id']]['childs'][$key]; 
			}
		}
		
		// varre o menu em busca de pais sem filho e sem ação (botão estético vazio)
		foreach($menu as $key => $button)
		{
			if(!isset($button['childs']) && empty($button['controller']) && empty($button['action']))
			{
				unset($menu[$key]);
			}
		}
		
		Cache::write("User.{$this->_user['User']['id']}.{$cacheKey}", $menu);
		
		return $menu;
	}
	
	/**
	 * Constroí menu utilizando como entrada um array com elementos em cascata (recursivos)
	 * 
	 * @param array $items
	 * @param array $cacheKey
	 */
	public function buildMini($items = array(), $cacheKey = 'ConfigMenu')
	{
		$menu = $this->deepCheck($items);
		
		// varre o menu em busca de pais sem filho e sem ação (botão estético vazio)
		foreach($menu as $key => $button)
		{
			if(!isset($button['childs']) && empty($button['controller']) && empty($button['action']))
			{
				unset($menu[$key]);
			}
		}
		
		Cache::write("User.{$this->_user['User']['id']}.{$cacheKey}", $menu);
		
		return $menu;
	}
	
	/**
	 * Verificação permissão para determinado item de menu/ação
	 * recursivamente.
	 * 
	 * @param array $items
	 */
	protected function deepCheck(&$items)
	{
		if(!is_array($items))
			return array();
			
		foreach($items as $k => $item)
		{
			if($this->checkMenuNode($item))
			{
				if(isset($item['childs']) && !empty($item['childs']))
					$item['childs'] = $this->deepCheck($item['childs']);
			}
			else
				unset($items[$k]);
		}
			
		return $items;
	}
	
	/**
	 * 
	 */
	protected function checkMenuNode($menu)
	{
		$aco = '';
		
		if(!empty($menu['plugin']))
			$aco .= Inflector::camelize($menu['plugin']);
			
		if(!empty($aco))
			$aco .= '/';
			
		if(!empty($menu['controller']))
			$aco .= Inflector::camelize($menu['controller']);
		
		if(!empty($aco))
			$aco .= '/';
			
		if(!empty($menu['action']))
			$aco .= $menu['action'];
		
		// Caso o item de menu não tenha url, ele é autorizado por padrão
		if(empty($aco))
			return true;
			
		$aro = '_' . $this->_user['User']['username'];
		
		return $this->Acl->check($aro, $aco);
	}
	
	/**
	 * Busca e retorna a permissão para um nó na
	 * árvore de acos
	 * 
	 * @param string $alias
	 */
	protected function getAcoNode($alias = '')
	{
		if(empty($this->settings['acoTree']))
			return false;
			
		$alias = Inflector::camelize($alias);
		
		foreach($this->settings['acoTree']['aplication'] as $node)
		{
			return $this->deepSearch($node, $term);
		}
	}
	
	/**
	 * Busca em profundidade, recursiva
	 * 
	 * @param array $nodes
	 * @param string $term
	 */
	protected function deepSearch($nodes, $term)
	{
		if($nodes['alias'] == $term)
		{
			return (bool)$nodes['authorized'];
		}
		
		if(isset($nodes['childs']))
		{
			$authorized = true;
			
			foreach($nodes['childs'] as $child)
			{
				$authorized = $authorized && $this->deepSearch($child, $term);
			}
			
			return $authorized;
		}
		
		return false;
	}
}