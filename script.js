/* 
========================================================================
   EMPLOYEE FEEDBACK DASHBOARD JAVASCRIPT
   Author: Internship Project
   Contains:
   - Feedback question datasets for each feedback type
   - switch-case based rendering function
   - DOM event listeners for select-type change
   - Form submission interceptor and custom success dialog
========================================================================
*/

// --- 1. QUESTION DATASETS ---

// Manager Feedback Questions
var facultyQuestions = [
    "1.The institution provides a supportive environment for effective teaching and learning?",
    "2.The department communicates academic policies and important information clearly and on time?",
    "3.Classroom facilities, laboratories, and teaching resources are adequate for conducting classes effectively?",
    "4.The management is approachable and responds promptly to faculty concerns and suggestions?",
    "5.The institution provides sufficient opportunities for professional development through workshops, seminars, and Faculty Development Programs (FDPs)?",
    "6.The workload assigned to me is reasonable and well balanced?",
    "7.Students actively participate in classroom activities and maintain good discipline?",
    "8.The institution provides reliable technological support, including internet access, LMS, and other digital teaching tools?",
    "9.I am satisfied with the overall work environment and collaboration among faculty members?",
    "10.Overall, I am satisfied with my experience working at this institution?"
];

// HR Feedback Questions
var infrastructureQuestions = [
    "1.How would you rate the cleanliness and maintenance of the classrooms?",
    "2.Are the classroom desks, chairs, and whiteboards comfortable and well-maintained?",
    "3.How satisfied are you with the ventilation, lighting, and temperature control in buildings?",
    "4.Does the campus Wi-Fi provide reliable coverage and sufficient speed for your academic work?",
    "5.How well-equipped are the computer labs and science laboratories with up-to-date equipment?",
    "6.How would you rate the availability of study spaces, seating, and resources in the library?",
    "7.Are the campus restrooms clean, well-stocked, and regularly maintained?",
    "8.How satisfied are you with the quality, hygiene, and seating capacity of the college cafeteria?",
    "9.How well do the campus sports facilities, gym, and recreational areas meet your needs?",
    "10.Does the campus feel safe, secure, and easy to navigate for individuals with disabilities?"
];
// Department Feedback Questions
var departmentQuestions = [
    "How well does your department collaborate to achieve common goals?",
    "Are there sufficient opportunities to learn new skills in your department?",
    "How would you rate the communication of decisions within your department?",
    "Is the workload distributed fairly among team members in your department?",
    "Does your department have access to the necessary tools and resources to work effectively?",
    "How encouraging is the environment in your department to try new approaches?",
    "Overall, how satisfied are you with the culture and performance of your department?"
];

var departmentQuestions = [
    "How well does your department collaborate to achieve common goals?",
    "Are there sufficient opportunities to learn new skills in your department?",
    "How would you rate the communication of decisions within your department?",
    "Is the workload distributed fairly among team members in your department?",
    "Does your department have access to the necessary tools and resources to work effectively?",
    "How encouraging is the environment in your department to try new approaches?",
    "Overall, how satisfied are you with the culture and performance of your department?"
];

// Standard Likert scale options
var scaleOptions = [
    "Strongly Disagree",
    "Disagree",
    "Neutral",
    "Agree",
    "Strongly Agree"
];

// --- 2. DOM EVENT LISTENERS ---

// Wait for the DOM to load fully before running script logic
document.addEventListener("DOMContentLoaded", function() {
    var selectElement = document.getElementById("feedbackTypeSelect");
    
    // Add change event listener for feedback type dropdown
    selectElement.addEventListener("change", function() {
        var selectedValue = selectElement.value;
        loadFeedbackForm(selectedValue);
    });

    // Intercept form submission
    var feedbackForm = document.getElementById("employeeFeedbackForm");
    feedbackForm.addEventListener("submit", function(event) {
        event.preventDefault(); // Prevent standard page reload

        // Trigger success message modal
        var successModalElement = document.getElementById("successModal");
        var successModalInstance = new bootstrap.Modal(successModalElement);
        successModalInstance.show();

        // Clear the form and dropdown selections
        resetFeedbackForm();
    });
});

// --- 3. DYNAMIC FORM GENERATION LOGIC ---

/**
 * Loads and generates the HTML structure of the selected feedback form dynamically
 * Uses a basic switch-case logic as requested.
 * @param {string} feedbackType 
 */
function loadFeedbackForm(feedbackType) {
    var formSection = document.getElementById("feedbackFormSection");
    var formContent = document.getElementById("formDynamicContent");

    // If no type is selected, hide the form section and return
    if (!feedbackType) {
        formSection.style.display = "none";
        return;
    }

    var headingHTML = "";
    var dropdownsHTML = "";
    var questionsHTML = "";
    var selectedQuestions = [];

    // Switch case to load content details based on selected type
    switch (feedbackType) {
        case "faculty":
            headingHTML = generateHeaderHTML("fa-user-tie", "Faculty Feedback");
            selectedQuestions = facultyQuestions;
            break;

        case "infrastructure":
            headingHTML = generateHeaderHTML("fa-users-cog", "HR Feedback");
            selectedQuestions = infrastructureQuestions;
            break;

        case "department":
            headingHTML = generateHeaderHTML("fa-sitemap", "Department Feedback");          
            selectedQuestions = subjectQuestions;
            break;

        case "environment":
            headingHTML = generateHeaderHTML("fa-leaf", "Work Environment Feedback");          
            selectedQuestions = workEnvironmentQuestions;
            break;

        case "training":
            headingHTML = generateHeaderHTML("fa-book-reader", "Training Feedback");
            dropdownsHTML = generateDropdownsHTML(
                "Training Course Name",
                ["-- Select Course --", "Advanced Javascript Workshop", "UI/UX Design Systems", "Agile & Scrum Methodologies", "Communication & Leadership Skills"],
                "Trainer Name",
                ["-- Select Trainer --", "Prof. Alan Turing", "Grace Hopper", "Dr. Ada Lovelace"]
            );
            selectedQuestions = trainingQuestions;
            break;

        default:
            formSection.style.display = "none";
            return;
    }

    // Build the questions HTML blocks
    for (var i = 0; i < selectedQuestions.length; i++) {
        questionsHTML += generateQuestionCardHTML(i + 1, selectedQuestions[i]);
    }

    // Combine sections together
    var fullHTML = headingHTML + dropdownsHTML + questionsHTML;

    // Insert into DOM and reveal form
    formContent.innerHTML = fullHTML;
    formSection.style.display = "block";

    // Scroll smoothly to form heading on mobile
    formSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

// --- 4. HTML GENERATION UTILITY FUNCTIONS ---

/**
 * Generates the header banner HTML for the form
 */
function generateHeaderHTML(iconClass, titleText) {
    return `
        <div class="form-header-container">
            <div class="form-header-icon">
                <i class="fas ${iconClass}"></i>
            </div>
            <h4 class="form-header-title">${titleText}</h4>
        </div>
    `;
}

/**
 * Generates the HTML for two required select dropdowns side by side
 */
function generateDropdownsHTML(label1, options1, label2, options2) {
    var select1Options = "";
    var select2Options = "";

    for (var i = 0; i < options1.length; i++) {
        select1Options += `<option value="${options1[i]}">${options1[i]}</option>`;
    }
    for (var j = 0; j < options2.length; j++) {
        select2Options += `<option value="${options2[j]}">${options2[j]}</option>`;
    }

    return `
        <div class="row form-dropdowns-row">
            <div class="col-md-6 mb-3 mb-md-0 form-dropdown-col">
                <label for="formSelect1" class="form-label">${label1} <span>*</span></label>
                <select class="form-select form-select-custom" id="formSelect1" required>
                    ${select1Options}
                </select>
            </div>
            <div class="col-md-6 form-dropdown-col">
                <label for="formSelect2" class="form-label">${label2} <span>*</span></label>
                <select class="form-select form-select-custom" id="formSelect2" required>
                    ${select2Options}
                </select>
            </div>
        </div>
    `;
}

/**
 * Generates the HTML card representing a single question and its five horizontal radio button options
 */
function generateQuestionCardHTML(number, questionText) {
    var radioGroupName = "question_" + number;
    var optionsHTML = "";

    for (var i = 0; i < scaleOptions.length; i++) {
        var optionId = "q_" + number + "_opt_" + i;
        var isRequired = (i === 0) ? "required" : ""; // Make the radio group mandatory by adding 'required' on the first item
        optionsHTML += `
            <div class="option-item">
                <input class="form-check-input option-radio" type="radio" name="${radioGroupName}" id="${optionId}" value="${scaleOptions[i]}" ${isRequired}>
                <label class="form-check-label option-label" for="${optionId}">
                    ${scaleOptions[i]}
                </label>
            </div>
        `;
    }

    return `
        <div class="question-card">
            <div class="question-title-wrapper">
                <div class="question-number">${number}</div>
                <h5 class="question-text">${questionText}</h5>
            </div>
            <div class="options-container">
                ${optionsHTML}
            </div>
        </div>
    `;
}

// --- 5. CLEANUP / RESET FUNCTION ---

/**
 * Resets the entire form fields, clears selections, and hides the form block
 */
function resetFeedbackForm() {
    var selectElement = document.getElementById("feedbackTypeSelect");
    var feedbackForm = document.getElementById("employeeFeedbackForm");
    
    // Clear text suggestions
    var suggestionsTextarea = document.getElementById("suggestionsTextarea");
    if (suggestionsTextarea) {
        suggestionsTextarea.value = "";
    }
    
    // Reset inputs
    feedbackForm.reset();
    
    // Clear and reset main dropdown selection
    selectElement.value = "";
    
    // Hide the dynamic form section
    var formSection = document.getElementById("feedbackFormSection");
    formSection.style.display = "none";
}
