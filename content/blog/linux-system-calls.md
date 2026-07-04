---
title: "What are System Calls in Linux?. System calls in Linux are the interface…"
date: 2025-06-10
summary: "Full local version of my Medium post on What are System Calls in Linux?. System calls in Linux are the interface…."
tags: ["DevOps", "SRE", "Infrastructure"]
---

# What are System Calls in Linux?

3 min read

·

Oct 17, 2024

\--

Share

System calls in Linux are the interface between a running process and the operating system’s kernel. They allow user-level processes to request services from the kernel, such as accessing hardware, managing memory, and performing file operations. Essentially, system calls enable user applications to communicate with the operating system.

## Key Points:

  * **System calls** allow programs to interact with the operating system’s kernel.
  * Linux provides hundreds of system calls for different purposes like file management, process control, memory allocation, etc.
  * Common system calls include `open()`, `read()`, `write()`, `fork()`, `exec()`, and `exit()`.

## How System Calls Work:

When a user program needs to perform a task that requires kernel-level access (such as reading a file or creating a process), it issues a system call. The CPU switches from **user mode** to **kernel mode** , and the corresponding function in the kernel is executed. After the operation is completed, control is returned to the user mode.

## Example of System Calls in Linux

### 1\. Open a File (`open()`)

To open a file, a process needs to request the kernel for access using the `open()` system call.
    
    
    #include <fcntl.h>  
    #include <unistd.h>  
      
    int main() {  
        int fd = open("example.txt", O_RDONLY);  
        if (fd == -1) {  
          
            return -1;  
        }  
        close(fd);  
        return 0;  
    }

> example.txt opened in read-only mode, return error if opening fails; close the file

  * `**open()**` is a system call that opens a file and returns a file descriptor.
  * `**close()**` is another system call that closes the file.

### 2\. Read from a File (`read()`)

The `read()` system call is used to read data from a file.
    
    
    #include <fcntl.h>  
    #include <unistd.h>  
      
    int main() {  
        int fd = open("example.txt", O_RDONLY);  
        char buffer[100];  
        ssize_t bytesRead = read(fd, buffer, sizeof(buffer));  // read up to 100 bytes  
        if (bytesRead == -1) {  
            // Handle error  
            return -1;  
        }  
        close(fd);  
        return 0;  
    }

  * `**read()**` reads data from the file descriptor `fd` and stores it in the buffer.
  * `**bytesRead**` stores the number of bytes actually read.

### 3\. Creating a New Process (`fork()`)

The `fork()` system call is used to create a new process by duplicating the current process.
    
    
    #include <fcntl.h>  
    #include <unistd.h>  
      
    int main() {  
        pid_t pid = fork();  // Create a new process  
        if (pid == -1) {  
            // Handle error  
            return -1;  
        }  
        if (pid == 0) {  
            // Child process  
            printf("This is the child process.\n");  
        } else {  
            // Parent process  
            printf("This is the parent process. Child PID: %d\n", pid);  
        }  
        return 0;  
    }

  * `**fork()**` creates a new process (child process). The child process receives a PID of 0, and the parent process gets the PID of the child.

### 4\. Executing a Program (`exec()`)

The `exec()` system call replaces the current process with a new program.
    
    
    #include <unistd.h>  
      
    int main() {  
        char *args[] = {"/bin/ls", NULL};  
        execvp(args[0], args);  // Replace current process with "ls" command  
        return 0;  
    }

  * `**execvp()**` replaces the current process with the `ls` command.

## Common Linux System Calls:

**System CallDescription**`open()`Open a file.`read()`Read data from a file or input stream.`write()`Write data to a file or output stream.`fork()`Create a new process.`exec()`Replace the current process with a new program.`close()`Close a file descriptor.`exit()`Terminate a process.`wait()`Wait for a child process to terminate.`getpid()`Get the process ID of the current process.`kill()`Send a signal to a process.

## Conclusion:

System calls are a vital mechanism in Linux that allow applications to interact with the hardware and manage system resources. They are the only way user-space programs can request services from the kernel. Understanding system calls is essential for system programming, especially in developing applications that directly interact with the operating system.
