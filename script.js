const signUpButton=document.getElementById('signUpButton');
const signInButton=document.getElementById('signInButton');
const signInForm=document.getElementById('signIn');
const signUpForm=document.getElementById('signup');

// signUpButton.addEventListener('click',function(){
//     signInForm.style.display="none";
//     signUpForm.style.display="block";
// })
// signInButton.addEventListener('click', function(){
//     signInForm.style.display="block";
//     signUpForm.style.display="none";
// })

// show the password use the eye icon
document.querySelectorAll('.toggle-password').forEach(icon => { 
    icon.addEventListener('click', function(){
        const input=this.previousElementSibling;
        if(input.type=="password"){
            input.type='text';
            this.innerHTML='<i class="fas fa-eye-slash"></i>';
        }else{
            input.type="password";
            this.innerHTML='<i class="fas fa-eye"></i>';
            }
        });
});

// document.querySelectorAll("#editableTable td").forEach(cell => {
//   cell.addEventListener("click", () => {
//     let currentText = cell.innerText;
//     let input = document.createElement("input");
//     input.value = currentText;
//     cell.innerText = "";
//     cell.appendChild(input);
//     input.focus();

//     input.addEventListener("blur", () => {
//       cell.innerText = input.value;
//     });

//     input.addEventListener("keydown", (e) => {
//       if (e.key === "Enter") {
//         input.blur();
//       }
//     });
//   });
// });

// For import Courses
(function (){
  var chooseBtn = document.getElementByUd('btn-import-choose');
  var fileInput = document.getElementByUd('import-file');
  var chosenOut = document.getElementByUd('import-chosen-file');

  if(!chooseBtn || !fileInput || !chosenOut) return;

  // click button -> trigger to choose the file
  chooseBtn.addEventListener('click', function (){
    fileInput.click();
  });

  // after choosing the file -> show the filename
  fileInput.addEventListener('change', function(){
    if(fileInput.files && fileInput.files.length >0) {
      var f = fileInput.files[0];
      chosenOut.textContent = 'Selected: ' + f.name;
    } else {
      chosenOut.textContent = '';
    }
  });
})();