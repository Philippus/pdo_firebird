<?php
class FirebirdTest extends PHPUnit_Framework_TestCase {

    protected $db;

    public function setUp()
    {
        $this->db = new PDO ("firebird:dbname=TEST.FDB", 'SYSDBA', 'masterkey');
        $this->db->exec('DROP TABLE testuser;'); // make sure it is not available
        $this->db->exec('DROP TABLE names;'); // make sure it is not available
        $query_create_table = <<< EOD
CREATE TABLE testuser (
  ID INTEGER NOT NULL,
  NAME VARCHAR(100) NOT NULL,
  ADDRESS VARCHAR(100) NOT NULL,
  COMPANY VARCHAR(100) NOT NULL
);
EOD;
        $this->db->exec($query_create_table);
        $this->db->exec('ALTER TABLE testuser ADD CONSTRAINT INTEG_13 PRIMARY KEY (ID);');
    }

    public function tearDown() {
        $this->db = null;
    }

    /**
     * Test if Firebird PDO can handle the PDO::ERRMODE_EXCEPTION attribute
     * @expectedException PDOException
     */
    public function testErrorModeException()
    {
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->db->exec("INSERT INTO testuser (ID, NAME, ADDRESS, COMPANY) VALUES ('BOGUS_PK', 'a', 'b', 'c')");
    }

    /**
     * Test if Firebird PDO can handle the PDO::ERRMODE_WARNING attribute
     * @expectedException PHPUnit_Framework_Error_Warning
     */
    public function testErrorModeWarning()
    {
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
        $this->db->exec("INSERT INTO testuser (ID, NAME, ADDRESS, COMPANY) VALUES ('BOGUS_PK', 'a', 'b', 'c')");
    }

    /**
     * Test if Firebird PDO can handle the PDO::ERRMODE_SILENT attribute
     */
    public function testErrorModeSilent()
    {
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);
        $this->db->exec("INSERT INTO testuser (ID, NAME, ADDRESS, COMPANY) VALUES ('BOGUS_PK', 'a', 'b', 'c')");
        $this->AssertTrue(count($this->db->errorInfo()) > 0);
    }

    /**
     * Test some pdo attributes for which we know the expected value
     * @dataProvider dbAttributesProvider
     * @param $expected
     * @param $dbAttribute
     */
    public function testDBAttributes($expected, $dbAttribute)
    {
        $result = $this->db->getAttribute($dbAttribute);
        $this->assertEquals($expected, $result);
    }

    public function dbAttributesProvider()
    {
        return array(
            array(1, PDO::ATTR_CONNECTION_STATUS),
            array('firebird', PDO::ATTR_DRIVER_NAME),
        );
    }

    /**
     * Test some pdo attributes for which we don't know the expected value
     * @dataProvider otherDbAttributesProvider
     * @param $dbAttribute
     */
    public function testOtherDBAttributes($dbAttribute)
    {
        $result = $this->db->getAttribute($dbAttribute);
        $this->assertTrue(strlen($result) > 0);
    }

    public function otherDbAttributesProvider()
    {
        return array(
            array(PDO::ATTR_SERVER_VERSION),
            array(PDO::ATTR_CLIENT_VERSION),
            array(PDO::ATTR_SERVER_INFO)
        );
    }

    /**
     * Test if we can insert an entry into the database with a standard SQL query
     */
    public function testStandardInsertQuery()
    {
        $insertResult = $this->db->exec(
            "INSERT INTO testuser (ID, NAME, ADDRESS, COMPANY) VALUES (1, 'user1', 'address1', 'company1')"
        );
        $this->assertTrue($insertResult !== false);
        $expected = array(0 => array('ID' => 1, 'NAME' => 'user1', 'ADDRESS' => 'address1', 'COMPANY' => 'company1'));
        $result = $this->db->query('SELECT * FROM testuser')->fetchAll(PDO::FETCH_ASSOC);
        $this->assertEquals($expected, $result);
    }

    /**
     * Test if we can use a prepared query with named parameters
     */
    public function testPreparedQueryWithNamedParameters() {
        $query = 'INSERT INTO testuser (ID, NAME, ADDRESS, COMPANY) VALUES (:ID, :NAME, :ADDRESS, :COMPANY)';
        $stmt = $this->db->prepare($query);
        $values = array(
            ':ID'      => 2,
            ':NAME'    => 'user2',
            ':ADDRESS' => 'address2',
            ':COMPANY' => 'company2'
        );
        $insertResult = $stmt->execute($values);
        $this->assertTrue($insertResult !== false);
        $expected = array(0 => array('ID' => 2, 'NAME' => 'user2', 'ADDRESS' => 'address2', 'COMPANY' => 'company2'));
        $result = $this->db->query('SELECT * FROM testuser')->fetchAll(PDO::FETCH_ASSOC);
        $this->assertEquals($expected, $result);
    }

    /**
     * Test if we can use a prepared query with ordered parameters
     */
    public function testPreparedQueryWithOrderedParameters() {
        $query = 'INSERT INTO testuser (ID, NAME, ADDRESS, COMPANY) VALUES (?, ?, ?, ?)';
        $stmt = $this->db->prepare($query);
        $values = array(
            0 => 3,
            1 => 'user3',
            2 => 'address3',
            3 => 'company3'
        );
        $insertResult = $stmt->execute($values);
        $this->assertTrue($insertResult !== false);
        $expected = array(0 => array('ID' => 3, 'NAME' => 'user3', 'ADDRESS' => 'address3', 'COMPANY' => 'company3'));
        $result = $this->db->query('SELECT * FROM testuser')->fetchAll(PDO::FETCH_ASSOC);
        $this->assertEquals($expected, $result);
    }

    /**
     * Tests if we can use PDO::ATTR_CASE
     */
    public function testPortabilityOptionsLowerCase() {
        $insertResult = $this->db->exec(
            "INSERT INTO testuser (ID, NAME, ADDRESS, COMPANY) VALUES (1, 'user1', 'address1', 'company1')"
        );
        $this->assertTrue($insertResult !== false);

        $this->db->setAttribute(PDO::ATTR_CASE, PDO::CASE_LOWER);
        $expected = array(0 => array('id' => 1, 'name' => 'user1', 'address' => 'address1', 'company' => 'company1'));
        $result = $this->db->query('SELECT * FROM testuser')->fetchAll(PDO::FETCH_ASSOC);
        $this->assertEquals($expected, $result);
    }

    /**
     * Tests if we can use PDO::ATTR_FETCH_TABLE_NAMES
     */
    public function testPortabilityOptionsTableNames() {
        $insertResult = $this->db->exec(
            "INSERT INTO testuser (ID, NAME, ADDRESS, COMPANY) VALUES (1, 'user1', 'address1', 'company1')"
        );
        $this->assertTrue($insertResult !== false);

        $this->db->setAttribute(PDO::ATTR_FETCH_TABLE_NAMES, 1);
        $expected = array(0 => array('TESTUSER.ID' => 1,
                                     'TESTUSER.NAME' => 'user1',
                                     'TESTUSER.ADDRESS' => 'address1',
                                     'TESTUSER.COMPANY' => 'company1'));
        $result = $this->db->query('SELECT * FROM testuser')->fetchAll(PDO::FETCH_ASSOC);
        $this->assertEquals($expected, $result);
    }

    public function testVariableBindingByName() {
        $this->db->exec("CREATE TABLE names(name VARCHAR(255) NOT NULL, surname VARCHAR(255));");
        $stmt = $this->db->prepare("insert into names (name, surname) values (:name, :surname)");
        // bind php variables to the named placeholders in the query
        // they are both strings that will not be more than 64 chars long
        $stmt->bindParam(':name',    $name,    PDO::PARAM_STR, 64);
        $stmt->bindParam(':surname', $surname, PDO::PARAM_STR, 64);
        // insert a record
        $name = 'Foo';
        $surname = 'Bar';
        $stmt->execute();
        // and another
        $name = 'Fu';
        $surname = 'Ba';
        $stmt->execute();
        $stmt = null;
        $result = $this->db->query('SELECT * FROM names')->fetchAll(PDO::FETCH_ASSOC);
        $expected = array(0 => array('NAME' => 'Foo', 'SURNAME' => 'Bar'),
                          1 => array('NAME' => 'Fu', 'SURNAME' => 'Ba'));
        $this->assertEquals($expected, $result);
    }

    public function testVariableBindingByPosition() {
        $this->db->exec("CREATE TABLE names(name VARCHAR(255) NOT NULL, surname VARCHAR(255));");
        $stmt = $this->db->prepare("insert into names (name, surname) values (:name, :surname)");
        // bind php variables to the named placeholders in the query
        // they are both strings that will not be more than 64 chars long
        $stmt->bindParam(1,    $name,    PDO::PARAM_STR, 64);
        $stmt->bindParam(2, $surname, PDO::PARAM_STR, 64);
        // insert a record
        $name = 'Foo';
        $surname = 'Bar';
        $stmt->execute();
        // and another
        $name = 'Fu';
        $surname = 'Ba';
        $stmt->execute();
        $stmt = null;
        $result = $this->db->query('SELECT * FROM names')->fetchAll(PDO::FETCH_ASSOC);
        $expected = array(0 => array('NAME' => 'Foo', 'SURNAME' => 'Bar'),
                          1 => array('NAME' => 'Fu', 'SURNAME' => 'Ba'));
        $this->assertEquals($expected, $result);
    }
}