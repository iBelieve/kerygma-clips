import "./bootstrap";

import segmentWordEditor from "./components/segment-word-editor.js";
import videoFraming from "./components/video-framing.js";
import viewTranscript from "./components/view-transcript.js";

document.addEventListener("alpine:init", () => {
    window.Alpine.data("segmentWordEditor", segmentWordEditor);
    window.Alpine.data("videoFraming", videoFraming);
    window.Alpine.data("viewTranscript", viewTranscript);
});
