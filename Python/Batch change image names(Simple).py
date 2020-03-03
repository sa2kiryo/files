#!/usr/bin/env python
# coding: utf-8

# In[14]:


import glob
import os

#同じルールで、フォルダ内の画像名を変更する場合
path = '*.jpg'

flist = glob.glob(path)

for file in flist :
    file_re = file.replace('de_','')
    
    os.rename(file,file_re.replace('_00',''))


# In[ ]:




