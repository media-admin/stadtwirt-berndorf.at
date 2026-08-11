import { useEffect, useMemo, useState } from "@wordpress/element";
import { __ } from "@wordpress/i18n"
import { getCF7Forms } from "../../api/api";
import { EditOutlined } from "@mui/icons-material";
import { Button } from "@wordpress/components";
import CF7AppsSkeletonLoader from "../../components/CF7AppsSkeletonLoader";
import CF7AppsSpamCount from '../../components/CF7AppsSpamCount';

const PER_PAGE = 10;

const CF7AppsHoneypotForms = () => {
    const [cf7forms, setCF7Forms] = useState(false);
    const [loading, setLoading] = useState(true);
    const [currentPage, setCurrentPage] = useState(1);

    useEffect(() => {
        async function fetchForms() {
            setLoading(true);
            const forms = await getCF7Forms();

            setCF7Forms(forms);
            setLoading(false);
        }

        fetchForms();
    }, []);

    const totalForms = cf7forms ? cf7forms.length : 0;
    const pages = Math.max(1, Math.ceil(totalForms / PER_PAGE));

    const paginatedForms = useMemo(() => {
        if (!cf7forms || !cf7forms.length) {
            return [];
        }

        const start = (currentPage - 1) * PER_PAGE;
        return cf7forms.slice(start, start + PER_PAGE);
    }, [cf7forms, currentPage]);

    const handlePageClick = (page) => {
        if (page < 1 || page > pages) {
            return;
        }

        setCurrentPage(page);
    };

    const renderPagination = () => {
        const paginationItems = [];

        if (totalForms) {
            if (pages <= 5) {
                for (let i = 1; i <= pages; i++) {
                    paginationItems.push(
                        <Button
                            key={i}
                            className={currentPage === i ? 'cf7apps-btn tertiary-secondary' : ''}
                            onClick={() => handlePageClick(i)}
                        >{i}</Button>
                    );
                }
            } else {
                paginationItems.push(
                    <Button
                        key={1}
                        className={currentPage === 1 ? 'cf7apps-btn tertiary-secondary' : ''}
                        onClick={() => handlePageClick(1)}
                    >1</Button>
                );

                if (currentPage > 3) {
                    paginationItems.push(
                        <Button key="start-ellipsis" disabled>...</Button>
                    );
                }

                for (let i = Math.max(2, currentPage - 1); i <= Math.min(pages - 1, currentPage + 1); i++) {
                    paginationItems.push(
                        <Button
                            key={i}
                            className={currentPage === i ? 'cf7apps-btn tertiary-secondary' : ''}
                            onClick={() => handlePageClick(i)}
                        >{i}</Button>
                    );
                }

                if (currentPage < pages - 2) {
                    paginationItems.push(
                        <Button key="end-ellipsis" disabled>...</Button>
                    );
                }

                paginationItems.push(
                    <Button
                        key={pages}
                        className={currentPage === pages ? 'cf7apps-btn tertiary-secondary' : ''}
                        onClick={() => handlePageClick(pages)}
                    >{pages}</Button>
                );
            }
        }

        return (
            <div className="cf7apps-honeypot-forms-pagination">
                <Button
                    className="cf7apps-btn tertiary-secondary"
                    disabled={currentPage === 1}
                    onClick={() => handlePageClick(currentPage - 1)}
                >&lt;</Button>
                {paginationItems}
                <Button
                    className="cf7apps-btn tertiary-secondary"
                    disabled={currentPage === pages}
                    onClick={() => handlePageClick(currentPage + 1)}
                >&gt;</Button>
            </div>
        );
    };

    return (
        !loading ?
            <div className="cf7apps-template-honeypot-forms">
                <table width="100%">
                    <thead>
                        <tr>
                            <th>{__('Title', 'cf7apps')}</th>
                            <th>{__('Shortcode', 'cf7apps')}</th>
                            <th>{__('Honeypot', 'cf7apps')}</th>
                            <th>{__('Date & Time', 'cf7apps')}</th>
                            <th>{__('Action', 'cf7apps')}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {
                            paginatedForms.length > 0 ? (
                                paginatedForms.map((form) => (
                                    <tr key={form.id}>
                                        <td>{form.title}</td>
                                        <td>{form.shortcode}</td>
                                        <td>{form.honeypot}</td>
                                        <td>{form.date}</td>
                                        <td>
                                            <Button href={form.action} className="cf7apps-btn tertiary-secondary">
                                                <EditOutlined /> {__('Edit', 'cf7apps')}
                                            </Button>
                                        </td>
                                    </tr>
                                ))
                            ) : (
                                <tr>
                                    <td colSpan="5">{__('No forms found.', 'cf7apps')}</td>
                                </tr>
                            )
                        }
                    </tbody>
                </table>

                <div className="cf7apps-table-nav cf7apps-nav-footer">
                    <div className="cf7apps-left">
                        <p className="cf7apps-datatable-count">
                            {__('Showing', 'cf7apps')} <b>{paginatedForms.length}</b> {__('of', 'cf7apps')} <b>{totalForms}</b> {__('entries', 'cf7apps')}
                        </p>
                    </div>
                    <div className="cf7apps-right">
                        {renderPagination()}
                    </div>
                </div>

                <CF7AppsSpamCount />
            </div>
            :
            <>
                <CF7AppsSkeletonLoader count={1} height={38} />
                <CF7AppsSkeletonLoader count={2} height={57} />
            </>
    );
}

export default CF7AppsHoneypotForms;
