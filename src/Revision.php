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

/**
 * Revision is returned from {@link AuditReader::getRevisions()}
 */
class Revision
{
    public const TYPE_ADD = 'INS'; 
    public const TYPE_UPDATE = 'UPD'; 
    public const TYPE_DELETE = 'DEL'; 

    private bool $validated = false ;

    function __construct(private $rev, private \DateTime $timestamp, private ?string $user_username, private $user_id, private ?string $user_firstName, private ?string $user_lastName, private $project)
    {
    }

    /**
     * @return int|string
     */
    public function getRev()
    {
        return $this->rev;
    }

    /**
     * @return \DateTime
     */
    public function getTimestamp()
    {
        return $this->timestamp;
    }

    public function getUserId()
    {
        return $this->user_id;
    }

    /**
     * @return string|null
     */
    public function getUserFirstname()
    {
        return $this->user_firstName;
    }

    /**
     * @return string|null
     */
    public function getUserLastName()
    {
        return $this->user_lastName;
    }

    public function getUsername()
    {
        return $this->user_username;
    }

    /**
     * @return bool
     */
    public function isValidated()
    {
        return $this->validated;
    }
}