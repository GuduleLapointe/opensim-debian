<?php
class Database
{
        var $host;
        var $user;
        var $password;
        var $db;

        var $mysql;

        function Database($config)
        {
                $this->host=$config["host"];
                $this->user=$config["user"];
                $this->password=$config["password"];
                $this->db=$config["db"];

                $this->mysql=mysql_connect($this->host, $this->user, $this->password, true) or die(mysql_error());
                mysql_select_db($this->db, $this->mysql) or die(mysql_error());
        }

        function query($sql)
        {
                $result=mysql_query($sql, $this->mysql) or die(mysql_error());

                return $result;
        }

        function execute($sql)
        {
                mysql_query($sql, $this->mysql);
                return mysql_error();
        }

        function insert($sql)
        {
                $this->execute($sql);
                if(mysql_error($this->mysql) != "")
                        return false;
                return mysql_insert_id($this->mysql);
        }

        function query_one($sql)
        {
                $result=$this->query($sql);

                $row=mysql_fetch_array($result);

                mysql_free_result($result) or die(mysql_error());

                return $row;
        }
}
?>
