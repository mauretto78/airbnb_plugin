


(function() {

    $.extend(UI, {

    });
    
    function overrideTabMessages( SegmentTabMessages ) {
        SegmentTabMessages.prototype.getNotes = function (  ) {
            let notesHtml = [];
            let self = this;
            if (this.props.notes) {
                this.props.notes.forEach(function (item, index) {
                    if ( item.note && item.note !== "" ) {
                        if (item.note.indexOf("¶") === -1) {
                            let split = item.note.split(":");
                            if ( split.length > 1) {
                                let html = <div className="note" key={"note-" + index}>
                                    <span className="note-label">{split[0]}:</span>
                                    <span dangerouslySetInnerHTML={self.allowHTML( split[1] )}/>
                                </div>;
                                notesHtml.push( html );
                            }
                        }

                    }
                });
            }
            if (notesHtml.length === 0) {
                let html = <div className="note" key={"note-0"}>
                    There are no notes available
                </div>;
                notesHtml.push(html);
            }
            return notesHtml;
        }
    }
    
    overrideTabMessages(SegmentTabMessages);
    
})() ;