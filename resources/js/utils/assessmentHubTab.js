export const initialAssessmentHubTab = (canReviewFcTopics) =>
    canReviewFcTopics ? 'topics' : 'results';

export const availableAssessmentHubTab = (selectedTab, canReviewResults) =>
    selectedTab === 'results' && !canReviewResults ? 'topics' : selectedTab;
