export const assessmentReviewRoster = (assignments = [], users = []) => {
    const usersById = new Map(users.map((user) => [Number(user.db_id), user]));

    return assignments.map((assignment) => {
        const user = usersById.get(Number(assignment.employeeId)) || {
            db_id: assignment.employeeId,
            sso: `review-${assignment.employeeId}`,
            t: '',
            n: assignment.employeeName,
            p: assignment.position,
            approvalOrg: assignment.organization,
            competencyGaps: assignment.allCompetencies || [],
            assignedCompetencies: [],
            act: true,
        };

        return {
            ...user,
            competencyGaps: assignment.allCompetencies ?? user.competencyGaps ?? [],
            assignedCompetencies: assignment.assignedCompetencies ?? user.assignedCompetencies ?? [],
            assessmentAssignment: assignment,
        };
    });
};

export const isPendingAssessmentReview = (person) => Boolean(
    person.assessmentAssignment?.isPending
    || person.results?.some((row) => row.workflowStatus?.isCurrentReviewer),
);

export const isInProgressAssessmentReview = (person) => person.statusMeta?.key === 'in_progress';
