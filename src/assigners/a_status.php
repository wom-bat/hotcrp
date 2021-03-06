<?php
// a_status.php -- HotCRP assignment helper classes
// HotCRP is Copyright (c) 2006-2017 Eddie Kohler and Regents of the UC
// Distributed under an MIT-like license; see LICENSE

class Status_AssignmentParser extends UserlessAssignmentParser {
    private $xtype;
    function __construct($aj) {
        parent::__construct("status");
        $this->xtype = $aj->type;
    }
    function allow_paper(PaperInfo $prow, AssignmentState $state) {
        // XXX allow contact to do stuff
        // XXX check permissions
        return $state->user->can_administer($prow);
    }
    function load_state(AssignmentState $state) {
        if (!$state->mark_type("status", ["pid"], "Status_Assigner::make"))
            return;
        foreach ($state->prows() as $prow)
            $state->load(["type" => "status", "pid" => $prow->paperId,
                          "_submitted" => (int) $prow->timeSubmitted,
                          "_withdrawn" => (int) $prow->timeWithdrawn,
                          "_withdraw_reason" => $prow->withdrawReason]);
    }
    function apply(PaperInfo $prow, Contact $contact, &$req, AssignmentState $state) {
        global $Now;
        $m = $state->remove(["type" => "status", "pid" => $prow->paperId]);
        $res = $m[0];
        if ($this->xtype === "submit") {
            if ($res["_submitted"] === 0)
                $res["_submitted"] = ($res["_withdrawn"] > 0 ? -$Now : $Now);
        } else if ($this->xtype === "unsubmit") {
            if ($res["_submitted"] !== 0)
                $res["_submitted"] = 0;
        } else if ($this->xtype === "withdraw") {
            if ($res["_withdrawn"] === 0) {
                assert($res["_submitted"] >= 0);
                $res["_withdrawn"] = $Now;
                $res["_submitted"] = -$res["_submitted"];
            }
            $r = (string) get($req, "withdraw_reason", get($req, "reason", null));
            if ($r !== "")
                $res["_withdraw_reason"] = $r;
            // XXX should update tags
        } else if ($this->xtype === "revive") {
            if ($res["_withdrawn"] !== 0) {
                assert($res["_submitted"] <= 0);
                $res["_withdrawn"] = 0;
                if ($res["_submitted"] === -100)
                    $res["_submitted"] = $Now;
                else
                    $res["_submitted"] = -$res["_submitted"];
                $res["_withdraw_reason"] = null;
            }
        }
        $state->add($res);
    }
}

class Status_Assigner extends Assigner {
    function __construct(AssignmentItem $item, AssignmentState $state) {
        parent::__construct($item, $state);
    }
    static function make(AssignmentItem $item, AssignmentState $state) {
        return new Status_Assigner($item, $state);
    }
    private function status_html($type) {
        if ($this->item->get($type, "_withdrawn"))
            return "Withdrawn";
        else if ($this->item->get($type, "_submitted"))
            return "Submitted";
        else
            return "Not ready";
    }
    function unparse_display(AssignmentSet $aset) {
        return '<del>' . $this->status_html(true) . '</del> '
            . '<ins>' . $this->status_html(false) . '</ins>';
    }
    function unparse_csv(AssignmentSet $aset, AssignmentCsv $acsv) {
        $x = [];
        if (($this->item->get(true, "_submitted") === 0) !== ($this->item["_submitted"] === 0))
            $x[] = ["pid" => $this->pid, "action" => $this->item["_submitted"] === 0 ? "unsubmit" : "submit"];
        if ($this->item->get(true, "_withdrawn") === 0 && $this->item["_withdrawn"] !== 0)
            $x[] = ["pid" => $this->pid, "action" => "revive"];
        else if ($this->item->get(true, "_withdrawn") !== 0 && $this->item["_withdrawn"] === 0) {
            $y = ["pid" => $this->pid, "action" => "withdraw"];
            if ((string) $this->item["_withdraw_reason"] !== "")
                $y["withdraw_reason"] = $this->item["_withdraw_reason"];
            $x[] = $y;
        }
        return $x;
    }
    function add_locks(AssignmentSet $aset, &$locks) {
        $locks["Paper"] = "write";
    }
    function execute(AssignmentSet $aset) {
        global $Now;
        $submitted = $this->item["_submitted"];
        $aset->conf->qe("update Paper set timeSubmitted=?, timeWithdrawn=?, withdrawReason=? where paperId=?", $submitted, $this->item["_withdrawn"], $this->item["_withdraw_reason"], $this->pid);
        if (($submitted > 0) !== ($this->item->get(true, "_submitted") > 0))
            $aset->cleanup_callback("papersub", function ($aset, $vals) {
                $aset->conf->update_papersub_setting(min($vals));
            }, $submitted > 0 ? 1 : 0);
    }
}
