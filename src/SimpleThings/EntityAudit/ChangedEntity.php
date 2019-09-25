<?php
/*
 * (c) 2011 SimpleThings GmbH
 *
 * @package SimpleThings\EntityAudit
 * @author Benjamin Eberlei <eberlei@simplethings.de>
 * @link http://www.simplethings.de
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 2.1 of the License, or (at your option) any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA
 */

namespace SimpleThings\EntityAudit;

class ChangedEntity
{
    private $className;
    private $id;
    private $revType;
    private $entity;
    private $rev;

    private $user_id;
    private $user_username;
    private $user_firstName;
    private $user_lastName;

    private $timestamp;
    
    public function __construct($className, array $id, $revType, $entity, $rev = null, $timestamp = null,
                                $user_id=null, $user_username=null, $user_firstName=null, $user_lastName=null)
    {
        $this->className = $className;
        $this->id = $id;
        $this->revType = $revType;
        $this->entity = $entity;
        $this->rev = $rev;
        $this->timestamp = $timestamp;

        $this->user_id = $user_id;
        $this->user_username = $user_username;
        $this->user_firstName = $user_firstName;
        $this->user_lastName = $user_lastName;
    }
    
    /**
     * @return string
     */
    public function getClassName()
    {
        return $this->className;
    }

    /**
     *
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     *
     * @return array
     */
    public function getTimestamp()
    {
        return $this->timestamp;
    }

    /**
     * @return string
     */
    public function getRevisionType()
    {
        return $this->revType;
    }

    /**
     * @return object
     */
    public function getEntity()
    {
        return $this->entity;
    }

    /**
     * @return object
     */
     public function getRevision()
     {
         return $this->rev;
     }

    public function getUserId()
    {
        return $this->user_id;
    }

    public function getUserFirstname()
    {
        return $this->user_firstName;
    }

    public function getUserLastName()
    {
        return $this->user_lastName;
    }

    public function getUserUsername()
    {
        return $this->user_username;
    }

    public function getUserDisplayName()
    {
        if( ($this->user_firstName!='' && $this->user_firstName!=null) || ($this->user_lastName!='' && $this->user_lastName!=null)) {

            $parts = array();
            if(($this->user_firstName!='' && $this->user_firstName!=null)) {
                $parts[] = $this->user_firstName;
            }
            if(($this->user_lastName!='' && $this->user_lastName!=null)) {
                $parts[] = $this->user_lastName;
            }
            return implode(' ', $parts);
        }
        else {
            return $this->user_username;
        }
    }
}