import { useState } from "@wordpress/element";
import { __ } from "@wordpress/i18n";

const CF7AppsHelpText = (props) => {
    const [showMore, setShowMore] = useState(false);
    const { description } = props;

    return (
        <>
            {
                (description !== undefined && description != 'undefined') && (
                    <p>
                        {typeof description === 'string' && description.length > 90 ? (
                            <>
                                {showMore ? description : `${description.substring(0, 90)} ...`}
                                <button
                                    type="button"
                                    className="cf7apps-show-more-text"
                                    onClick={() => setShowMore(!showMore)}
                                >
                                    {showMore ? ` ${__('Show Less', 'cf7apps')}` : ` ${__('Show More', 'cf7apps')}`}
                                </button>
                            </>
                        ) : (
                            description
                        )}
                    </p>
                )
            }
        </>
    );
}

export default CF7AppsHelpText;