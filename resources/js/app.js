import "./bootstrap";

import readonlyTranscript from "./components/readonly-transcript.js";
import videoFraming from "./components/video-framing.js";
import viewTranscript from "./components/view-transcript.js";

document.addEventListener("alpine:init", () => {
    window.Alpine.data("readonlyTranscript", readonlyTranscript);
    window.Alpine.data("videoFraming", videoFraming);
    window.Alpine.data("viewTranscript", viewTranscript);
});
