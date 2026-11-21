export default class Paginator {

    public static readonly MIN_PAGE = 1;
    public static readonly MIN_PER_PAGE = 5;
    public static readonly MAX_PER_PAGE = 100;

    private readonly _maxPage: number;

    constructor(
        public readonly page?: number | null,
        public readonly perPage?: number | null,
        public readonly total?: number | null,
    ) {
        this.page = Paginator.normalizePage(this.page);
        this.perPage = Paginator.normalizePerPage(this.perPage);

        if (!this.total || this.total <= 0) {
            this.total = 0;
        }

        this._maxPage = Math.ceil(this.total / this.perPage);
    }

    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    static normalizePage(page: any): number {
        page = Number(page);

        if (!page || page <= 0) {
            page = Paginator.MIN_PAGE;
        }

        return <number>Math.floor(page);
    }

    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    static normalizePerPage(perPage: any): number {
        perPage = Number(perPage);

        if (!perPage || perPage <= 0) {
            perPage = Paginator.MIN_PER_PAGE;
        }

        if (perPage > Paginator.MAX_PER_PAGE) {
            perPage = Paginator.MAX_PER_PAGE;
        }

        return <number>Math.floor(perPage);
    }

    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    static normalizeBoolean(value: any): boolean | null {
        if (value === 'true' || value === '1' || value === 1) {
            return true;
        }

        if (value === 'false' || value === '0' || value === 0) {
            return false;
        }

        return null;
    }

}
