<?php

namespace Servdebt\SlimCore\Utils;
use LdapRecord\Connection;

class Ldap
{

    /** @var Connection $ldapConn */
	private Connection $ldapConn;


	public function __construct($params)
	{
        $this->ldapConn = new Connection([
            'hosts'            => [$params['host']],
            'base_dn'          => $params['baseDn'],
            'username'         => $params['username'],
            'password'         => $params['password'],
        ]);
	}


    public function getUser($username, array $memberOf = []) :?array
    {
        $query = $this->ldapConn->query();
        $results = $query->where('samaccountname', $username)->get();

        if (empty($results) || count($results) != 1) {
            return null;
        }

        return $this->isMemberOfAll($results[0], $memberOf) ? $results[0] : null;
    }


    public function authenticateUser($distinguishedname, $password) :bool
    {
        return $this->ldapConn->auth()->attempt($distinguishedname, $password);
    }


    public function isMemberOfAny($elem, array $membership = []) :bool
    {
        return count(array_intersect($elem['memberof'] ?? [], $membership)) > 0;
    }


    public function isMemberOfAll($elem, array $membership = []) :bool
    {
        return count(array_intersect($elem['memberof'] ?? [], $membership)) == count($membership);
    }


    public function belongsToOu($elem, string $ou) :bool
    {
        return str_contains($elem['distinguishedname'][0]  ?? '', "OU={$ou}");
    }

}