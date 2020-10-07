<?php

/**
 * @Table('instructorprofile')
 */
class InstructorModel extends Model {

	/**
	 * @Key
	 * @Column('id')
	 */
	public $instructorid;

	/**
	 * @Column('department')
	 */
	public $department;

	/**
	 * @Column('preferred_title')
	 */
	public $name;

	/**
	 * @Column('visual_style')
	 */
    public $visual;

    /**
	 * @Column('auditory_style')
	 */
    public $auditory;

    /**
	 * @Column('read_write_style')
	 */
    public $readwrite;

    /**
	 * @Column('kinesthetic_style')
	 */
	public $kines;

	/**
	 * @Column('rating')
	 */
	public $rating;

	public function setUserId($userId) {
		$this->instructorid = $userId;
	}
}