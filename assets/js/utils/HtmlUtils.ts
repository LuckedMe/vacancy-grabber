export const nl2br = (text?: string | null): string => {
    if (!text) {
        return '';
    }
    return text.replace(/\n/g, '<br>');
};
