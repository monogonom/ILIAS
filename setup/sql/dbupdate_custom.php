<#1>
<?php
if(!$ilDB->tableColumnExists('exc_assignment','peer_char'))
{
	$ilDB->addTableColumn('exc_assignment', 'peer_char', array(
		'type' => 'integer',
		'length' => 2,
		'notnull' => false
	));
}
?>
<#2>
<?php
if(!$ilDB->tableColumnExists('exc_assignment','peer_unlock'))
{
	$ilDB->addTableColumn('exc_assignment', 'peer_unlock', array(
		'type' => 'integer',
		'length' => 1,
		'notnull' => true,
		'default' => 0
	));
}
?>
<#3>
<?php
if(!$ilDB->tableColumnExists('exc_assignment','peer_valid'))
{
	$ilDB->addTableColumn('exc_assignment', 'peer_valid', array(
		'type' => 'integer',
		'length' => 1,
		'notnull' => true,
		'default' => 1
	));
}
?>
<#4>
<?php
if(!$ilDB->tableColumnExists('exc_assignment','team_tutor'))
{
	$ilDB->addTableColumn('exc_assignment', 'team_tutor', array(
		'type' => 'integer',
		'length' => 1,
		'notnull' => true,
		'default' => 0
	));
}
?>
<#5>
<?php
if(!$ilDB->tableColumnExists('exc_assignment','max_file'))
{
	$ilDB->addTableColumn('exc_assignment', 'max_file', array(
		'type' => 'integer',
		'length' => 1,
		'notnull' => false
	));
}
?>
<#6>
<?php
if(!$ilDB->tableColumnExists('exc_assignment','deadline2'))
{
	$ilDB->addTableColumn('exc_assignment', 'deadline2', array(
		'type' => 'integer',
		'length' => 4,
		'notnull' => false
	));
}
?>
<#7>
<?php
	$ilCtrlStructureReader->getStructure();
?>
<#8>
<?php
if(!$ilDB->tableColumnExists('exc_returned','late'))
{
	$ilDB->addTableColumn('exc_returned', 'late', array(
		'type' => 'integer',
		'length' => 1,
		'notnull' => true,
		'default' => 0
	));
}
?>