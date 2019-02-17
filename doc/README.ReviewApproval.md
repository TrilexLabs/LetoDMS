Review/Approval
------------------

The traditional Review/Approval process has been around for a long time
already and is still available, though the new workflow engine has been
introduced.

Review/Approval is a very simple, but in many cases sufficient, workflow
to review and approve document versions. Review and approval is done by users
in behalf of themself or the group they belong to. A document version
is reviewed or approved if all users and groups in charge of it, have
found the document version to be ready and worth for release. If a single
user rejects it, it will not change its status to 'released' but to 'rejected'.

Review is always done before approval, but a document version may not have 
to run through both processes.
A version can use just the approval process without a review before,
and it can also skip the approval process and just run through review. In
the second case the document will be released immidiately after successful
review.

If a group is in charge for reviewing/approving a document, it will be
sufficient if one member of that group takes action.

Internally LetoDMS keeps a record of all approval/review actions done on
a document version. When a document version is uploaded with both processes
in place, then for each user/group a list of log entries is created. Any
action on the document will add a new entry to the log. Those entries
contain the action (release/reject), a user comment and the current date.
Each entry will also trigger an internal function which checks, whether
the last action causes a document state change. Such a state change happens
when all reviews or approvals are done, or if a review/approval is rejected.
If a user or a group is deleted and some documents are still waiting for
a review/approval, this will also be logged and the review/approval will
no longer be needed to release the document.

Before a document leaves the approval or review state any review/approval
or reject can be changed. So if a user initially approves a document and
later changes her/his mind, he/she can still reject the document.
