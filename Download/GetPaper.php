<?php 
//
// GetPaper -- this is a PHP script where execution is specified in a .htaccess
// file. This is done so the paper that is being requested is specified as a
// suffix to the GetPaper request. It's necessary to have automatic file naming
// work for specific browsers (I think Mozilla/netscape).
//
require_once('../Code/confHeader.inc');
$Conf->connect();
$Me = $_SESSION['Me'];
$Me->goIfInvalid();

//
// Determine the intended paper
//
if (isset($_REQUEST['paperId']))
    $paperId = cvtint($_REQUEST["paperId"]);
else {
    $paper = preg_replace("|.*/GetPaper/*|", "", $_SERVER["PHP_SELF"]);
    if (preg_match("/^(" . $Conf->downloadPrefix . ")?(paper-?)?(\d+).*$/", $paper, $match)
	&& $match[3] > 0)
	$paperId = $match[3];
    else
	$Error = "Invalid paper name '" . htmlspecialchars($paper) . "'.";
}

//
// Security checks - people who can download all paperss
// are assistants, chairs & PC members. Otherwise, you need
// to be a contact person for that paper.
//
if (!isset($Error) && !$Me->canViewPaper($paperId, $Conf, $whyNot))
    $Error = whyNotText($whyNot, "view");

//
// Actually download paper.
//
if (!isset($Error)) {
    $result = $Conf->downloadPaper($paperId, cvtint($_REQUEST['save']) > 0);
    if (!PEAR::isError($result))
	exit;
}

//
// If we get here, there is an error.
//
$Conf->header("Download Paper #$paperId");
if (isset($Error))
    $Conf->errorMsg($Error);

$Conf->footer() ?>
