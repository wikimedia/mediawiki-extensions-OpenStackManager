<?php

/**
 * Class for interacting with puppet groups, variables and classes
 *
 * @file
 * @ingroup Extensions
 */

class OpenStackNovaPuppetGroup {

	private $id, $name, $is_global, $vars, $classes;

	/**
	 * Constructor. Can't be called directly. Call one of the static NewFrom* methods
	 * @param $id Int Database id for the group
	 * @param $name String User-defined name of the group
	 * @param $position int
	 * @param $project string|null
	 */
	public function __construct( $id, $name, $is_global, $project=null ) {
		$this->id = $id;
		$this->name = $name;
		$this->is_global = $is_global;
		$this->project = $project;
		$this->loadVars( $id );
		$this->loadClasses( $id );
	}

	/**
	 * @return String
	 */
	public function getName() {
		return $this->name;
	}

	/**
	 * @return Boolean
	 */
	public function getIsGlobal() {
		return $this->is_global;
	}

	/**
	 * @return Int
	 */
	public function getId() {
		return $this->id;
	}

	public function getVars() {
		return $this->vars;
	}

	public function getClasses() {
		return $this->classes;
	}

	/**
	 * @return string
	 */
	public function getProject() {
		return $this->project;
	}

	/**
	 * @param $name string
	 * @return OpenStackNovaPuppetGroup|null
	 */
	public static function newFromName( $name, $project=null ) {
		$dbr = wfGetDB( DB_SLAVE );
		$row = $dbr->selectRow(
			'openstack_puppet_groups',
			array(  'group_id',
				'group_name',
				'group_project',
		       		'group_is_global' ),
			array(  'group_name' => $name,
	       			'group_project' => $project ),
			__METHOD__ );

		if ( $row ) {
			return self::newFromRow( $row );
		} else {
			return null;
		}
	}

	/**
	 * @param $id int
	 * @return OpenStackNovaPuppetGroup|null
	 */
	public static function newFromId( $id ) {
		$dbr = wfGetDB( DB_SLAVE );
		$row = $dbr->selectRow(
			'openstack_puppet_groups',
			array( 
				'group_id',
				'group_name',
		       		'group_is_global' ),
			array( 'group_id' => intval( $id ) ),
			__METHOD__ );

		if ( $row ) {
			return self::newFromRow( $row );
		} else {
			return null;
		}
	}

	/**
	 * @param $id int
	 * @return OpenStackNovaPuppetGroup|null
	 */
	public static function newFromClassId( $id ) {
		$dbr = wfGetDB( DB_SLAVE );
		$row = $dbr->selectRow(
			'openstack_puppet_classes',
			array( 'class_group_id' ),
			array( 'class_id' => intval( $id ) ),
			__METHOD__ );
		if ( $row ) {
			return self::newFromId( $row->class_group_id );
		} else {
			return null;
		}
	}

	/**
	 * @param $id int
	 * @return OpenStackNovaPuppetGroup|null
	 */
	public static function newFromVarId( $id ) {
		$dbr = wfGetDB( DB_SLAVE );
		$row = $dbr->selectRow(
			'openstack_puppet_vars',
			array( 'var_group_id' ),
			array( 'var_id' => intval( $id ) ),
			__METHOD__ );
		if ( $row ) {
			return self::newFromId( $row->var_group_id );
		} else {
			return null;
		}
	}

	/**
	 * @param $row
	 * @return OpenStackNovaPuppetGroup
	 */
	static function newFromRow( $row ) {
		return new OpenStackNovaPuppetGroup(
			intval( $row->group_id ),
			$row->group_name,
			$row->group_is_global,
			$row->group_project
		);
	}

	/**
	 * @param $projects array Optionally get list for a set of projects
	 * @return array
	 */
	public static function getGroupList( $project='' ) {
		$dbr = wfGetDB( DB_SLAVE );
		if ( $project ) {
			$condition = 'group_project = ' . $dbr->addQuotes( $project );
		} else {
			$condition = 'group_is_global = true';
		}
		$rows = $dbr->select(
			'openstack_puppet_groups',
			array(  'group_id',
				'group_name',
				'group_project',
		       		'group_is_global' ),
			$condition,
			__METHOD__,
			array( 'ORDER BY' => 'group_name ASC' )
		);
		$groups = array();
		foreach ( $rows as $row ) {
			$groups[] = self::newFromRow( $row );
		}
		return $groups;
	}

	/**
	 * @param $groupid Int Group id of puppet variables
	 */
	function loadVars( $groupid ) {
		$dbr = wfGetDB( DB_SLAVE );
		$rows = $dbr->select(
			'openstack_puppet_vars',
			array(  'var_id',
				'var_name' ),
			array( 'var_group_id' => $groupid ),
			__METHOD__,
			array( 'ORDER BY' => 'var_name ASC' )
	       	);

		$this->vars = array();
		if ( $rows ) {
			foreach ( $rows as $row ) {
				$this->vars[] = array(
					"name" => $row->var_name,
					"id" => intval( $row->var_id ),
				);
			}
		}
	}

	/**
	 * @param $groupid Int Group id of puppet classes
	 */
	function loadClasses( $groupid ) {
		$dbr = wfGetDB( DB_SLAVE );
		$rows = $dbr->select(
			'openstack_puppet_classes',
			array(  'class_id',
				'class_name' ),
			array( 'class_group_id' => $groupid ),
			__METHOD__,
			array( 'ORDER BY' => 'class_name ASC' )
		);

		$this->classes = array();
		if ( $rows ) {
			foreach ( $rows as $row ) {
				$this->classes[] = array(
					"name" => $row->class_name,
					"id" => intval( $row->class_id ),
				);
			}
		}
	}

	/**
	 * @param $name string
	 * @return bool
	 */
	public static function addGroup( $name, $project='' ) {
		if ( $project ) {
			$group_is_global = false;
		} else {
			$group_is_global = true;
		}
		$dbw = wfGetDB( DB_MASTER );
		return $dbw->insert(
			'openstack_puppet_groups',
			array(  'group_name' => $name,
				'group_project' => $project,
				'group_is_global' => $group_is_global,
			),
			__METHOD__
		);
	}

	public static function addVar( $name, $groupid ) {
		$dbw = wfGetDB( DB_MASTER );
		return $dbw->insert(
			'openstack_puppet_vars',
			array(  'var_name' => $name,
				'var_group_id' => $groupid
			),
			__METHOD__
		);
	}

	public static function addClass( $name, $groupid ) {
		$dbw = wfGetDB( DB_MASTER );
		return $dbw->insert(
			'openstack_puppet_classes',
			array(  'class_name' => $name,
				'class_group_id' => $groupid
			),
			__METHOD__
		);
	}

	/**
	 * @param $id int
	 * @return bool
	 */
	public static function deleteVar( $id ) {
		$dbw = wfGetDB( DB_MASTER );
		return $dbw->delete(
			'openstack_puppet_vars',
			array( 'var_id' => $id ),
			__METHOD__
		);
	}

	/**
	 * @param $id int
	 * @return bool
	 */
	public static function deleteClass( $id ) {
		$dbw = wfGetDB( DB_MASTER );
		return $dbw->delete(
			'openstack_puppet_classes',
			array( 'class_id' => $id ),
			__METHOD__
		);
	}

	/**
	 * @param $id int
	 * @return bool
	 */
	public static function deleteGroup( $id ) {
		$dbw = wfGetDB( DB_MASTER );
		// TODO: stuff this into a transaction
		$dbw->delete(
			'openstack_puppet_vars',
			array( 'var_group_id' => $id ),
			__METHOD__
		);
		$dbw->delete(
			'openstack_puppet_classes',
			array( 'class_group_id' => $id ),
			__METHOD__
		);
		return $dbw->delete(
			'openstack_puppet_groups',
			array( 'group_id' => $id ),
			__METHOD__
		);
	}

	# TODO: add ability to update name
	public static function updateVar( $id, $groupid ) {
		$dbw = wfGetDB( DB_MASTER );
		return $dbw->update(
			'openstack_puppet_vars',
			array(
				'var_group_id' => $groupid
			),
			array( 'var_id' => $id ),
			__METHOD__
		);
	}

	# TODO: add ability to update name
	public static function updateClass( $id, $groupid ) {
		$dbw = wfGetDB( DB_MASTER );
		return $dbw->update(
			'openstack_puppet_classes',
			array(
				'class_group_id' => $groupid
			),
			array( 'class_id' => $id ),
			__METHOD__
		);
	}

}
