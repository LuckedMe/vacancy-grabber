import AppliedFilters from "../transport-response/AppliedFilters";

export default class VacancyInfoFilters extends AppliedFilters {
    constructor(
        page?: number | null ,
        perPage?: number | null,
        query?: string | null,
        sort?: string | null,
        // extends
        public viewed: boolean | null = null,
        public startDay: string | null = null,
        public endDay: string | null = null,
        public isResumeSent: boolean | null = null,
        public resumeSentStartDay: string | null = null,
        public resumeSentEndDay: string | null = null,

        public isDeadLine: boolean | null = null,
        public deadLineStartDay: string | null = null,
        public deadLineEndDay: string | null = null,

        public source: string | null = null,
        public company: string | null = null,
        public status: string | null = null,
    ) {
        super(
            page,
            perPage,
            query,
            sort,
        );

        const url = new URL(window.location.href);
        if (url.searchParams.has('not-viewed')) {
            url.searchParams.delete('not-viewed');
            url.searchParams.set('viewed', '0');

            // Updates the URL in the address bar without reloading the page
            window.history.replaceState({}, '', url);
        }

        this.processFromUrlParams(this);
    }
}
