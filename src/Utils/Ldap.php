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


    public function getUser(string $username, array $memberOf = []) :?array
    {
        $query = $this->ldapConn->query();
        $results = $query->where('samaccountname', $username)->get();

        if (empty($results) || count($results) != 1) {
            return null;
        }

        return $this->isMemberOfAll($results[0], $memberOf) ? $results[0] : null;
    }


    public function authenticateUser(string $distinguishedname, string $password) :bool
    {
        return $this->ldapConn->auth()->attempt($distinguishedname, $password);
    }


    public function isMemberOfAny(array $elem, array $membership = []) :bool
    {
        return count(array_intersect($elem['memberof'] ?? [], $membership)) > 0;
    }


    public function isMemberOfAll(array $elem, array $membership = []) :bool
    {
        return count(array_intersect($elem['memberof'] ?? [], $membership)) == count($membership);
    }


    public function belongsToOu(array $elem, string $ou) :bool
    {
        return isset($elem['distinguishedname'][0]) && str_contains($elem['distinguishedname'][0], "OU={$ou}");
    }

    public function getEntries(string $baseDn, array $filter, array $attributes=[]) :?array
    {
        return $this->ldapConn->query()->setDn($baseDn)->select($attributes)->rawFilter($filter)->get();
    }
}